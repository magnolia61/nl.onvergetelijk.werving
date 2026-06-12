<?php

namespace Civi\Werving;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * End-to-end test: Welke_leeftijdsgroep + Welke_kampweek → Welke_kampweken
 *
 * @group e2e
 *
 * Scenario: Een belangstellende vult het formulier in met:
 * - Welke leeftijdsgroep: "Tienerkamp (14-15)"
 * - Welke kampweek: "week1"
 *
 * Expectatie: Welke_kampweken moet automatisch worden berekend en gezet op
 * "\x01TK1\x01" (TK = TienerKamp, 1 = week 1).
 *
 * De berekening gebeurt direct in werving_civicrm_customPre() via parameter-injectie.
 *
 * REGRESSIE (2026-06): de berekening werkte alleen als leeftijdsgroep én kampweek
 * in DEZELFDE save binnenkwamen. Bij gescheiden saves (webform schrijft velden in
 * fasen, of een bestaand contact) faalde de DB-fallback in werving_civicrm_configure
 * omdat (a) base_cid2cont de werving_leeftijdsgroep/kampweek/kampweken-keys niet
 * mapte en (b) het checkbox-veld leeftijdsgroep als array binnenkwam en blind naar
 * (string) werd gecast. De testWelkeKampweken*EersteSave-tests dekken dat scenario af.
 */
class WelkeKampwekenDbTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  private int $contactId;

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('werving_civicrm_customPre')) {
      $this->markTestSkipped('werving_civicrm_customPre() niet beschikbaar; is nl.onvergetelijk.werving geïnstalleerd?');
    }

    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name'   => 'Test',
      'last_name'    => 'Kampweken',
      'birth_date'   => '2010-01-01',  // 14-15 jarig
    ])['id'];
  }

  /**
   * Simuleer webform-submit met welke_leeftijdsgroep en welke_kampweek.
   * Gebruikt Contact.create om de hook te triggeren (net als een echte webform).
   */
  private function stelWelkeKampwekenIn(): void {
    \civicrm_api3('Contact', 'create', [
      'id'               => $this->contactId,
      'custom_378'       => 'tienerkamp',  // Welke_leeftijdsgroep
      'custom_377'       => 'week1',       // Welke_kampweek
    ]);
  }

  /**
   * Zet één of meer werving-velden via een losse Contact.create (triggert de hook).
   * Simuleert een webform die velden in aparte saves wegschrijft.
   */
  private function stelVeldenIn(array $velden): void {
    \civicrm_api3('Contact', 'create', ['id' => $this->contactId] + $velden);
  }

  /**
   * Normaliseer de multiselect-waarde (array of \x01-string) naar één string
   * waarin we op de losse codes (TK1, KK2, ...) kunnen asserten.
   */
  private function normaliseerKampweken($value): string {
    if (is_array($value)) {
      return implode('', array_map(fn($v) => "\x01$v\x01", $value));
    }
    return (string) $value;
  }

  private function leesWelkeKampweken(): array {
    return \civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->contactId]],
      'select'           => [
        'WERVING.Welke_leeftijdsgroep',
        'WERVING.Welke_kampweek',
        'WERVING.Welke_kampweken',
      ],
    ])->first() ?? [];
  }

  // ########################################################################

  /**
   * Na invulling van leeftijdsgroep + kampweek moet welke_kampweken worden berekend.
   */
  public function testWelkeKampwekenWordtBerekendNaInvulling() {
    $this->stelWelkeKampwekenIn();
    $db = $this->leesWelkeKampweken();

    $this->assertNotEmpty($db['WERVING.Welke_kampweken'] ?? NULL,
      'Na invulling van leeftijdsgroep + kampweek moet welke_kampweken worden berekend en gevuld zijn.');
  }

  /**
   * Voor Tienerkamp week1 moet het resultaat "\x01TK1\x01" bevatten (multiselect formaat).
   */
  public function testWelkeKampwekenFormaat() {
    $this->stelWelkeKampwekenIn();
    $db = $this->leesWelkeKampweken();

    $value = $this->normaliseerKampweken($db['WERVING.Welke_kampweken'] ?? NULL);

    $this->assertStringContainsString('TK1', $value,
      "Voor Tienerkamp week1 moet welke_kampweken 'TK1' bevatten.");
  }

  /**
   * REGRESSIE: leeftijdsgroep in save 1, kampweek pas in save 2.
   * De berekening bij save 2 moet de leeftijdsgroep uit de DB-fallback halen.
   * Faalde vóór de fix (base_cid2cont mapte de key niet) → kampweken bleef leeg.
   */
  public function testWelkeKampwekenLeeftijdsgroepEersteSave() {
    $this->stelVeldenIn(['custom_378' => 'tienerkamp']);  // save 1: alleen leeftijdsgroep
    $this->stelVeldenIn(['custom_377' => 'week1']);        // save 2: alleen kampweek

    $value = $this->normaliseerKampweken($this->leesWelkeKampweken()['WERVING.Welke_kampweken'] ?? NULL);

    $this->assertStringContainsString('TK1', $value,
      'Kampweken moet ook berekend worden als leeftijdsgroep (save 1) en kampweek (save 2) gescheiden binnenkomen.');
  }

  /**
   * REGRESSIE: kampweek in save 1, leeftijdsgroep pas in save 2.
   * Spiegelbeeld van de vorige test (andere volgorde).
   */
  public function testWelkeKampwekenKampweekEersteSave() {
    $this->stelVeldenIn(['custom_377' => 'week2']);        // save 1: alleen kampweek
    $this->stelVeldenIn(['custom_378' => 'tienerkamp']);   // save 2: alleen leeftijdsgroep

    $value = $this->normaliseerKampweken($this->leesWelkeKampweken()['WERVING.Welke_kampweken'] ?? NULL);

    $this->assertStringContainsString('TK2', $value,
      'Kampweken moet ook berekend worden als kampweek (save 1) en leeftijdsgroep (save 2) gescheiden binnenkomen.');
  }

}
