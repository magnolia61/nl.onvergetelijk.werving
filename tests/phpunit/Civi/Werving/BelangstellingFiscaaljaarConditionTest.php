<?php

namespace Civi\Werving;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test de custom CiviRules-conditie CRM_Werving_CivirulesCondition_BelangstellingFiscaaljaar.
 *
 * @group e2e
 *
 * Deze conditie bewaakt CiviRule 575 ("BELANGSTELLING gaat mee", template 162
 * "nu aanmelden"): de mail mag alleen naar belangstellenden van de LOPENDE
 * wervingscyclus. De conditie geeft TRUE als WERVING.Datum_belangstelling in het
 * huidige boekjaar valt (OZK-boekjaar start 1 december; bepaald via de centrale
 * base-functie infiscalyear()).
 *
 * We evalueren de conditie rechtstreeks (isConditionValid) met een nagebouwde
 * TriggerData; zo testen we de logica zonder de rule te hoeven afvuren of mail
 * te sturen.
 */
class BelangstellingFiscaaljaarConditionTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  private int $contactId;

  public function setUp(): void {
    parent::setUp();
    if (!class_exists('CRM_Werving_CivirulesCondition_BelangstellingFiscaaljaar')) {
      $this->markTestSkipped('Conditie-class niet beschikbaar; is nl.onvergetelijk.werving geïnstalleerd?');
    }
    if (!class_exists('CRM_Civirules_TriggerData_Edit')) {
      $this->markTestSkipped('CiviRules niet beschikbaar.');
    }
    if (!function_exists('infiscalyear')) {
      $this->markTestSkipped('infiscalyear() niet beschikbaar; is nl.onvergetelijk.base geïnstalleerd?');
    }

    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name'   => 'Boekjaar',
      'last_name'    => 'Conditie',
      'birth_date'   => '1995-01-01',
    ])['id'];
  }

  /**
   * Zet Datum_belangstelling rechtstreeks op de WERVING-tabel via SQL.
   *
   * Bewust géén Contact.update: dat zou de hele werving-pipeline (configure,
   * activity, ACL) afvuren. Voor deze conditie-test willen we enkel de DB-waarde
   * zetten waar de conditie naar kijkt.
   */
  private function zetBelangstellingsdatum(?string $datum): void {
    if ($datum === NULL) {
      \CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_value_werving_270 SET datum_belangstelling_647 = NULL WHERE entity_id = %1',
        [1 => [$this->contactId, 'Integer']]
      );
      return;
    }
    // INSERT ... ON DUPLICATE: de WERVING-rij bestaat mogelijk nog niet.
    \CRM_Core_DAO::executeQuery(
      'INSERT INTO civicrm_value_werving_270 (entity_id, datum_belangstelling_647) VALUES (%1, %2) ' .
      'ON DUPLICATE KEY UPDATE datum_belangstelling_647 = %2',
      [
        1 => [$this->contactId, 'Integer'],
        2 => [$datum, 'String'],
      ]
    );
  }

  private function evalueerConditie(): bool {
    $triggerData = new \CRM_Civirules_TriggerData_Edit('Contact', $this->contactId, ['id' => $this->contactId], []);
    $triggerData->setContactId($this->contactId);
    $triggerData->setEntityId($this->contactId);

    $condition = new \CRM_Werving_CivirulesCondition_BelangstellingFiscaaljaar();
    // Parameterloze conditie: lege condition_params is voldoende.
    $condition->setRuleConditionData(['id' => 0, 'condition_params' => '']);

    return (bool) $condition->isConditionValid($triggerData);
  }

  // ########################################################################

  /**
   * Belangstelling van vandaag valt in het huidige boekjaar → TRUE.
   */
  public function testVerseBelangstellingGeeftTrue() {
    $this->zetBelangstellingsdatum(date('Y-m-d H:i:s'));
    $this->assertTrue($this->evalueerConditie(),
      'Belangstelling van vandaag moet binnen het huidige boekjaar vallen → conditie TRUE.');
  }

  /**
   * Belangstelling van 2 jaar geleden valt buiten het huidige boekjaar → FALSE.
   */
  public function testOudeBelangstellingGeeftFalse() {
    $this->zetBelangstellingsdatum(date('Y-m-d H:i:s', strtotime('-2 years')));
    $this->assertFalse($this->evalueerConditie(),
      'Belangstelling van 2 jaar geleden valt buiten het huidige boekjaar → conditie FALSE.');
  }

  /**
   * Geen belangstellingsdatum → FALSE (geen mail).
   */
  public function testLegeBelangstellingGeeftFalse() {
    $this->zetBelangstellingsdatum(NULL);
    $this->assertFalse($this->evalueerConditie(),
      'Zonder Datum_belangstelling mag de conditie niet TRUE geven.');
  }

}
