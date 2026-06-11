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

    $value = $db['WERVING.Welke_kampweken'] ?? NULL;
    // Multiselect kan array of string zijn
    if (is_array($value)) {
      $value = implode('', array_map(fn($v) => "\x01$v\x01", $value));
    }

    $this->assertStringContainsString('TK1', (string)$value,
      "Voor Tienerkamp week1 moet welke_kampweken 'TK1' bevatten.");
  }

}
