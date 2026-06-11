<?php

namespace Civi\Werving;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * End-to-end test: Datum_belangstelling invullen triggert CV + INTAKE.
 *
 * @group e2e
 *
 * Scenario: een nieuwe vrijwilliger vult het belangstellingsformulier in.
 * Dit zet WERVING.Datum_belangstelling. Na de DB-commit vuurt
 * werving_civicrm_custom(270) → roept cv_civicrm_configure en
 * intake_civicrm_configure aan.
 *
 * Verwachtingen na Contact.update met Datum_belangstelling:
 *   - Curriculum.Totaal_keren_mee = 0   (nog nooit meegeweest → CV aangemaakt met 0)
 *   - Curriculum.Keren_Deel       = 0
 *   - Curriculum.Keren_Leid       = 0
 *   - INTAKE.INT_nodig  = 'eerstex'      (eerste keer leiding, keer_leid=0)
 *   - INTAKE.INT_status = 'gedeeltelijk' (NAW/BIO nog niet gedaan)
 *   - INTAKE.NAW_nodig  gevuld (niet null)
 *   - INTAKE.BIO_nodig  gevuld (niet null)
 *   - ACL groep 855 ('OOIT Belangstelling [ACL]') → contact is lid (status 'Added')
 *
 * Waarom de volledige pipeline getest wordt (niet alleen cv+intake):
 *   WERVING (270) zit bewust NIET in profilecvmax — anders schrijft core terug
 *   naar WERVING en ontstaat een loop. Daardoor draait core nooit voor WERVING-saves.
 *   werving_civicrm_custom doet zelf de volledige pipeline: CV → INTAKE → ACCOUNT → ACL.
 *
 *   Klaas Varkevisser (CID 27453) had na het invullen van zijn belangstelling
 *   géén curriculum-record, géén intake-record en de verkeerde Drupal-rol
 *   (ooit_deelnemer i.p.v. ooit_belangstelling). Oorzaak: ACCOUNT + ACL werden
 *   niet aangeroepen vanuit werving_civicrm_custom. Deze test voorkomt herhaling.
 */
class DatumBelangstellingDbTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  private int $contactId;

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('werving_civicrm_custom')) {
      $this->markTestSkipped('werving_civicrm_custom() niet beschikbaar; is nl.onvergetelijk.werving geïnstalleerd?');
    }
    if (!function_exists('intake_civicrm_configure')) {
      $this->markTestSkipped('intake_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.intake geïnstalleerd?');
    }
    if (!function_exists('cv_civicrm_configure')) {
      $this->markTestSkipped('cv_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.cv geïnstalleerd?');
    }
    if (!function_exists('acl_civicrm_configure')) {
      $this->markTestSkipped('acl_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.acl geïnstalleerd?');
    }

    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name'   => 'Nieuwe',
      'last_name'    => 'Belangstelling',
      'birth_date'   => '2000-01-01',
    ])['id'];
  }

  private function stelDatumBelangstellingIn(): void {
    \civicrm_api4('Contact', 'update', [
      'checkPermissions' => FALSE,
      'values'           => [
        'id'                           => $this->contactId,
        'WERVING.Datum_belangstelling' => date('Y-m-d H:i:s'),
      ],
    ]);
  }

  private function leesIntake(): array {
    return \civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->contactId]],
      'select'           => [
        'INTAKE.INT_nodig',
        'INTAKE.INT_status',
        'INTAKE.NAW_nodig',
        'INTAKE.BIO_nodig',
      ],
    ])->first() ?? [];
  }

  private function leesCV(): array {
    return \civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->contactId]],
      'select'           => [
        'Curriculum.Totaal_keren_mee',
        'Curriculum.Keren_Deel',
        'Curriculum.Keren_Leid',
      ],
    ])->first() ?? [];
  }

  private function leesMeeStatus(): array {
    return \civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->contactId]],
      'select'           => [
        'WERVING.mee_status',
      ],
    ])->first() ?? [];
  }

  // ########################################################################

  /**
   * Na zetten van Datum_belangstelling moet INT_nodig='eerstex' in DB staan.
   */
  public function testIntNodigWordtEerstexNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $db = $this->leesIntake();

    $this->assertEquals('eerstex', $db['INTAKE.INT_nodig'] ?? NULL,
      'Na Datum_belangstelling moet INT_nodig="eerstex" in de DB staan.');
  }

  /**
   * Na zetten van Datum_belangstelling moet INT_status='gedeeltelijk' in DB staan.
   */
  public function testIntStatusWordtGedeeltelijkNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $db = $this->leesIntake();

    $this->assertEquals('gedeeltelijk', $db['INTAKE.INT_status'] ?? NULL,
      'Na Datum_belangstelling moet INT_status="gedeeltelijk" in de DB staan.');
  }

  /**
   * NAW_nodig en BIO_nodig moeten gevuld zijn na Datum_belangstelling.
   */
  public function testNawEnBioNodigGevuldNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $db = $this->leesIntake();

    $this->assertNotNull($db['INTAKE.NAW_nodig'] ?? NULL,
      'NAW_nodig moet gevuld zijn na Datum_belangstelling.');
    $this->assertNotNull($db['INTAKE.BIO_nodig'] ?? NULL,
      'BIO_nodig moet gevuld zijn na Datum_belangstelling.');
  }

  // ########################################################################
  // ### CV: CURRICULUM AANGEMAAKT MET TELLERS = 0
  // ########################################################################

  /**
   * Na zetten van Datum_belangstelling moet Curriculum.Totaal_keren_mee = 0 in DB staan.
   *
   * Achtergrond: cv_civicrm_configure() berekent de tellers op basis van de
   * participant-records. Een nieuwe vrijwilliger heeft geen kamp meegemaakt,
   * dus alle tellers zijn 0. Maar er MOET wel een curriculum-record bestaan —
   * anders mist de ACL de waarde en kent hij ten onrechte ooit_deelnemer toe
   * (zoals bij Klaas Varkevisser, CID 27453, op 27-05-2026).
   */
  public function testCurriculumTotaalKerenmeeIsNulNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $cv = $this->leesCV();

    $this->assertArrayHasKey('Curriculum.Totaal_keren_mee', $cv,
      'Curriculum.Totaal_keren_mee moet aanwezig zijn na cv_civicrm_configure().');
    $this->assertSame(0, (int) ($cv['Curriculum.Totaal_keren_mee'] ?? -1),
      'Nieuwe vrijwilliger zonder kampen moet Totaal_keren_mee=0 hebben.');
  }

  /**
   * Keren_Deel en Keren_Leid moeten 0 zijn na Datum_belangstelling.
   */
  public function testCurriculumKerenDeelEnLeidZijnNulNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $cv = $this->leesCV();

    $this->assertSame(0, (int) ($cv['Curriculum.Keren_Deel'] ?? -1),
      'Keren_Deel moet 0 zijn voor een nieuwe vrijwilliger.');
    $this->assertSame(0, (int) ($cv['Curriculum.Keren_Leid'] ?? -1),
      'Keren_Leid moet 0 zijn voor een nieuwe vrijwilliger.');
  }

  // ########################################################################
  // ### WERVING: MEE_STATUS INITIALISATIE
  // ########################################################################

  /**
   * Na Datum_belangstelling moet mee_status='onbekend' in DB staan.
   *
   * Achtergrond: Iemand die het belangstellingsformulier invult toont interesse,
   * maar we weten nog niet of ze echt willen deelnemen. Status 'onbekend' = "nog
   * geen contact gehad". De volgende stap is 'contact' (contact opnemen). Dit
   * triggert ook voor webforms (groupID=0) via de fallback in werving_civicrm_custom() stap 2.5.
   */
  public function testMeeStatusWordtOnbekendNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $werving = $this->leesMeeStatus();

    $this->assertEquals('onbekend', $werving['WERVING.mee_status'] ?? NULL,
      'Na Datum_belangstelling moet mee_status="onbekend" in de DB staan (interesse getoond, nog geen contact).');
  }

  /**
   * Na Datum_belangstelling moet mee_verwachting='misschien' in DB staan.
   *
   * Achtergrond: Iemand die belangstelling toont krijgt als verwachting 'misschien'
   * (we weten nog niet of ze echt zullen gaan). Dit geeft HR inzicht in de status
   * voordat persoonlijk contact is opgenomen.
   */
  public function testMeeVerwachtingWordtMisschienNaDatumBelangstelling() {
    $this->stelDatumBelangstellingIn();
    $werving = \civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->contactId]],
      'select'           => ['WERVING.mee_verwachting'],
    ])->first() ?? [];

    $this->assertEquals('misschien', $werving['WERVING.mee_verwachting'] ?? NULL,
      'Na Datum_belangstelling moet mee_verwachting="misschien" in de DB staan.');
  }

  // ########################################################################
  // ### ACL: GROEP 855 (OOIT BELANGSTELLING) MOET GEZET ZIJN
  // ########################################################################

  /**
   * Na Datum_belangstelling moet het contact lid zijn van ACL-groep 855.
   *
   * Dit is het bewijs dat acl_civicrm_configure() daadwerkelijk draaide als
   * onderdeel van de volledige pipeline in werving_civicrm_custom(). Zonder
   * deze stap krijgt een nieuw Drupal-account de hardcoded ooit_deelnemer-rol
   * mee en nooit de correcte ooit_belangstelling (zoals bij Klaas, CID 27453).
   */
  public function testAclGroep855GezettNaDatumBelangstelling() {
    $gidBelangstelling = 855;

    $groep = \civicrm_api4('Group', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $gidBelangstelling]],
      'select'           => ['id'],
    ])->first();

    if (empty($groep)) {
      $this->markTestSkipped("Groep $gidBelangstelling ('OOIT Belangstelling [ACL]') bestaat niet in deze omgeving.");
    }

    $this->stelDatumBelangstellingIn();

    $groupContact = \civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [
        ['contact_id', '=', $this->contactId],
        ['group_id',   '=', $gidBelangstelling],
        ['status',     '=', 'Added'],
      ],
      'select'           => ['status'],
    ])->first();

    $this->assertNotEmpty($groupContact,
      "Na Datum_belangstelling moet het contact in ACL-groep $gidBelangstelling (ooit_belangstelling) zitten. " .
      "Als deze test faalt, draait acl_civicrm_configure() niet vanuit werving_civicrm_custom()."
    );
  }

  // ########################################################################
  // ### WERVING_TRIGGER → CV (sqltask 236 failsafe-route)
  // ########################################################################

  /**
   * Update van WERVING.werving_trigger triggert cv_civicrm_configure via customPre.
   *
   * Dit is de route die sqltask 236 gebruikt als failsafe voor contacten die al
   * in het systeem zitten (jaaroverzicht aanwezig) maar nog nooit een
   * curriculum-record hadden (totaal_keren_mee IS NULL). De task update
   * werving_trigger → customPre stap 4.1 → cv_civicrm_configure → curriculum.
   *
   * Zonder dit pad zou de sqltask geen effect hebben voor zulke contacten.
   * Zie bug CID 27453 (2026-05-27) en sqltask 236.
   */
  public function testWervingTriggerMaaktCurriculumAanViaCv() {
    if (!function_exists('cv_civicrm_configure')) {
      $this->markTestSkipped('cv_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.cv geïnstalleerd?');
    }

    // Verwijder een eventueel auto-aangemaakt curriculum-record zodat we de
    // trigger-route kunnen testen vanuit een schone staat (geen record).
    \CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_value_curriculum_103 WHERE entity_id = %1',
      [1 => [$this->contactId, 'Integer']]
    );

    // Verifieer dat het record inderdaad weg is.
    $voor = \CRM_Core_DAO::executeQuery(
      'SELECT id FROM civicrm_value_curriculum_103 WHERE entity_id = %1',
      [1 => [$this->contactId, 'Integer']]
    );
    $this->assertFalse($voor->fetch(),
      'Curriculum-record moet vóór de trigger afwezig zijn (setUp-conditie).'
    );

    // Simuleer wat sqltask 236 doet: update werving_trigger.
    \civicrm_api4('Contact', 'update', [
      'checkPermissions' => FALSE,
      'values'           => [
        'id'                     => $this->contactId,
        'WERVING.werving_trigger' => date('Y-m-d H:i:s'),
      ],
    ]);

    // Na de trigger moet cv_civicrm_configure een curriculum-record aangemaakt hebben.
    $na = \CRM_Core_DAO::executeQuery(
      'SELECT id, totaal_keren_mee_458 FROM civicrm_value_curriculum_103 WHERE entity_id = %1',
      [1 => [$this->contactId, 'Integer']]
    );
    $this->assertTrue($na->fetch(),
      'Na WERVING.werving_trigger moet customPre → cv_civicrm_configure een curriculum-record aanmaken. ' .
      'Als deze test faalt, werkt stap 4.1 in werving_civicrm_customPre() niet.'
    );
    $this->assertSame(0, (int) $na->totaal_keren_mee_458,
      'Nieuw curriculum-record moet totaal_keren_mee=0 bevatten.'
    );
  }

}
