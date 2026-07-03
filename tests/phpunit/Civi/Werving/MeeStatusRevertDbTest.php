<?php

namespace Civi\Werving;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Regressietest: mee_status (en mee_verwachting/mee_toelichting) mogen NIET
 * gewist worden door een secundaire WERVING-save binnen hetzelfde request.
 *
 * @group e2e
 *
 * ACHTERGROND (bug 30-jun-2026):
 *   werving_civicrm_configure() leest velden die niet in het formulier zaten als
 *   DB-fallback uit base_cid2cont(). Die fallback gebruikte de ONGEPREFIXTE keys
 *   ($cont['mee_status'] etc.), terwijl base_cid2cont ze als $cont['werving_mee_*']
 *   teruggeeft — en WERVING.mee_status ontbrak zelfs volledig in de select.
 *   Daardoor was de fallback ALTIJD NULL. Gevolg: elke WERVING-save zónder die
 *   velden in de params (bv. een losse mee_toelichting-edit, of de
 *   mee_verwachting='misschien'-initialisatie in werving_civicrm_custom stap 2.5)
 *   liet configure new_mee_status=NULL injecteren → een zojuist gezette status
 *   zoals 'gaatmee' (CiviRule 575 / back-office) werd teruggedraaid.
 *
 * FIX:
 *   - base_cid2cont(): WERVING.mee_status toegevoegd aan select + output 'werving_mee_status'.
 *   - configure(): de 4 mee_*-fallbacks lezen nu $cont['werving_mee_*'].
 *
 * Deze tests reproduceren precies het scenario waarin de waarden eerder verdwenen.
 */
class MeeStatusRevertDbTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  private int $contactId;

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('werving_civicrm_configure')) {
      $this->markTestSkipped('werving_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.werving geïnstalleerd?');
    }
    if (!function_exists('base_cid2cont')) {
      $this->markTestSkipped('base_cid2cont() niet beschikbaar; is nl.onvergetelijk.base geïnstalleerd?');
    }

    // Volwassene (>=17): voorkomt dat de "poortwachter" in configure() de
    // wervingsvelden leegt omdat het contact te jong zou zijn voor leiding.
    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name'   => 'Revert',
      'last_name'    => 'Test',
      'birth_date'   => '1995-01-01',
    ])['id'];
  }

  private function leesWerving(): array {
    return \civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->contactId]],
      'select'           => [
        'WERVING.mee_status',
        'WERVING.mee_verwachting',
        'WERVING.mee_toelichting',
      ],
    ])->first() ?? [];
  }

  private function updateWerving(array $values): void {
    \civicrm_api4('Contact', 'update', [
      'checkPermissions' => FALSE,
      'values'           => array_merge(['id' => $this->contactId], $values),
    ]);
  }

  // ########################################################################
  // ### KERN-REGRESSIE: mee_status='gaatmee' blijft staan
  // ########################################################################

  /**
   * Een losse WERVING-save die mee_status NIET meestuurt mag een bestaande
   * 'gaatmee' niet terugdraaien naar NULL.
   *
   * Vóór de fix: de fallback $cont['mee_status'] was NULL → configure injecteerde
   * NULL → mee_status weg.
   */
  public function testGaatmeeBlijftBijLosseWervingSave() {
    // Zet de uitgangssituatie: belangstelling + verwachting gevuld (zodat stap 2.5
    // geen verwachting-update hoeft te doen) + status 'gaatmee'.
    $this->updateWerving([
      'WERVING.Datum_belangstelling' => date('Y-m-d H:i:s'),
      'WERVING.mee_verwachting'      => 'zekerwel',
      'WERVING.mee_status'           => 'gaatmee',
    ]);
    $this->assertEquals('gaatmee', $this->leesWerving()['WERVING.mee_status'] ?? NULL,
      'Voorwaarde: mee_status moet na de eerste save "gaatmee" zijn.');

    // Trigger nu een WERVING-save die mee_status NIET bevat (alleen mee_notities).
    $this->updateWerving(['WERVING.mee_notities' => 'losse aanpassing']);

    $this->assertEquals('gaatmee', $this->leesWerving()['WERVING.mee_status'] ?? NULL,
      'mee_status="gaatmee" moet behouden blijven na een WERVING-save zonder mee_status in de params. ' .
      'Faalt deze test, dan leest configure() de DB-fallback weer met de verkeerde key (regressie).');
  }

  /**
   * Een losse WERVING-save mag een bestaande mee_toelichting niet wissen.
   * (Zelfde root cause als mee_status; mee_toelichting is een pure passthrough.)
   */
  public function testMeeToelichtingBlijftBijLosseWervingSave() {
    $this->updateWerving([
      'WERVING.Datum_belangstelling' => date('Y-m-d H:i:s'),
      'WERVING.mee_verwachting'      => 'zekerwel',
      'WERVING.mee_toelichting'      => 'BELANGRIJKE NOTITIE niet wissen',
    ]);
    $this->assertEquals('BELANGRIJKE NOTITIE niet wissen', $this->leesWerving()['WERVING.mee_toelichting'] ?? NULL,
      'Voorwaarde: mee_toelichting moet na de eerste save gevuld zijn.');

    // WERVING-save zonder mee_toelichting in de params.
    $this->updateWerving(['WERVING.mee_notities' => 'andere aanpassing']);

    $this->assertEquals('BELANGRIJKE NOTITIE niet wissen', $this->leesWerving()['WERVING.mee_toelichting'] ?? NULL,
      'mee_toelichting moet behouden blijven na een WERVING-save zonder mee_toelichting in de params.');
  }

  // ########################################################################
  // ### BRON VAN DE BUG: base_cid2cont moet mee_status teruggeven
  // ########################################################################

  /**
   * base_cid2cont() moet de WERVING.mee_status-waarde teruggeven onder de key
   * 'werving_mee_status'. Ontbrak dit veld, dan was de configure-fallback NULL.
   */
  public function testBaseCid2contBevatWervingMeeStatus() {
    $this->updateWerving([
      'WERVING.Datum_belangstelling' => date('Y-m-d H:i:s'),
      'WERVING.mee_verwachting'      => 'zekerwel',
      'WERVING.mee_status'           => 'gaatmee',
    ]);

    // force_fresh=TRUE: verse lezing, niet uit de request-cache.
    $cont = \base_cid2cont($this->contactId, TRUE);

    $this->assertArrayHasKey('werving_mee_status', $cont,
      'base_cid2cont() moet de sleutel "werving_mee_status" teruggeven.');
    $this->assertEquals('gaatmee', $cont['werving_mee_status'] ?? NULL,
      'base_cid2cont() moet de actuele mee_status ("gaatmee") teruggeven.');
  }

}
