<?php

namespace Civi\Werving;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests voor de belangstellingsformulier-workflow in nl.onvergetelijk.werving.
 *
 * @group e2e
 *
 * Simuleert wat er gebeurt als een vrijwilliger het webformulier invult.
 * werving_civicrm_configure() is de rekenmachine — we roepen die rechtstreeks
 * aan met $context='hook' en $params_werving gevuld alsof een webform is ingediend.
 *
 * Scenario's:
 *
 * TEST 1 — Kampweken bepalen (sectie 3.1):
 *   - Welke_leeftijdsgroep='kinderkamp' + Welke_kampweek='week1' → \x01KK1\x01
 *   - Welke_leeftijdsgroep='kinderkamp' + Welke_kampweek='week2' → \x01KK2\x01
 *   - Welke_leeftijdsgroep='kinderkamp' + Welke_kampweek='maaktnietuit' → \x01KK1\x01KK2\x01
 *   - Welke_leeftijdsgroep='brugkamp'   + Welke_kampweek='week1' → \x01BK1\x01
 *   - Welke_leeftijdsgroep='tienerkamp' + Welke_kampweek='maaktnietuit' → \x01TK1\x01TK2\x01
 *   - Welke_leeftijdsgroep='jeugdkamp'  + Welke_kampweek='week2' → \x01JK2\x01
 *   - Geen groep/week → lege string
 *
 * TEST 2 — mee_verwachting initieel (sectie 3.3):
 *   - Eerste keer formulier (datum=vandaag, geen verwachting) → mee_verwachting='misschien'
 *   - Datum 3 maanden geleden (dit fiscaaljaar) + 'zekerniet' → reset naar 'misschien'
 *   - Datum 3 maanden geleden + 'waarschijnlijktniet' → reset naar 'misschien'
 *   - Datum 3 maanden geleden + lege verwachting → ook 'misschien'
 */
class BelanstellingFormulierTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  private int $contactId;

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('werving_civicrm_configure')) {
      $this->markTestSkipped('werving_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.werving geïnstalleerd?');
    }

    // Maak een volwassen vrijwilliger (geboortejaar 1990 → nooit te jong)
    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name'   => 'Werving',
      'last_name'    => 'Testpersoon',
      'birth_date'   => '1990-06-15',
    ])['id'];
  }

  // ########################################################################
  // ### TEST 1: KAMPWEKEN BEREKENING
  // ########################################################################

  /**
   * Kinderkamp week 1 → KK1
   */
  public function testKinderkampWeek1GeeftKK1() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week1',
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertStringContainsString("\x01KK1\x01", $kampweken,
      'kinderkamp + week1 moet KK1 opleveren.');
    $this->assertStringNotContainsString('KK2', $kampweken,
      'Alleen week1 gekozen: KK2 mag er niet in zitten.');
  }

  /**
   * Kinderkamp week 2 → KK2
   */
  public function testKinderkampWeek2GeeftKK2() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week2',
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertStringContainsString("\x01KK2\x01", $kampweken,
      'kinderkamp + week2 moet KK2 opleveren.');
    $this->assertStringNotContainsString('KK1', $kampweken,
      'Alleen week2 gekozen: KK1 mag er niet in zitten.');
  }

  /**
   * Kinderkamp maakt niet uit → KK1 én KK2
   */
  public function testKinderkampMaaktNietUitGeeftBeidekampen() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'maaktnietuit',
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertStringContainsString("\x01KK1\x01", $kampweken, 'KK1 moet aanwezig zijn.');
    $this->assertStringContainsString('KK2',         $kampweken, 'KK2 moet aanwezig zijn.');
  }

  /**
   * Brugkamp week 1 → BK1
   */
  public function testBrugkampWeek1GeeftBK1() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'brugkamp',
      'WERVING.Welke_kampweek'       => 'week1',
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertStringContainsString("\x01BK1\x01", $kampweken, 'brugkamp + week1 moet BK1 opleveren.');
  }

  /**
   * Tienerkamp maakt niet uit → TK1 én TK2
   */
  public function testTienerkampMaaktNietUitGeeftBeideWeken() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'tienerkamp',
      'WERVING.Welke_kampweek'       => 'maaktnietuit',
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertStringContainsString("\x01TK1\x01", $kampweken, 'TK1 moet aanwezig zijn.');
    $this->assertStringContainsString('TK2',         $kampweken, 'TK2 moet aanwezig zijn.');
  }

  /**
   * Jeugdkamp week 2 → JK2
   */
  public function testJeugdkampWeek2GeeftJK2() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'jeugdkamp',
      'WERVING.Welke_kampweek'       => 'week2',
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertStringContainsString("\x01JK2\x01", $kampweken, 'jeugdkamp + week2 moet JK2 opleveren.');
  }

  /**
   * Geen leeftijdsgroep en geen kampweek → lege kampweken string
   */
  public function testGeenGroepGeenWeekGeeftLegeKampweken() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
    ]);

    $kampweken = $result['WERVING.Welke_kampweken'] ?? '';
    $this->assertSame('', $kampweken,
      'Zonder leeftijdsgroep/kampweek moet Welke_kampweken een lege string zijn.');
  }

  // ########################################################################
  // ### TEST 2: MEE_VERWACHTING EN MEE_STATUS BIJ INITIËLE BELANGSTELLING
  // ########################################################################

  /**
   * Eerste keer formulier invullen (datum=vandaag, geen eerdere verwachting)
   * → mee_verwachting='misschien'
   *
   * Hook-mode refresht datum_belangstelling altijd naar vandaag (sectie 3.0),
   * dus datum valt altijd in het huidige fiscale jaar → sectie 3.3 zet verwachting
   * op 'misschien' als er nog geen definitieve aanmelding is.
   */
  public function testEerstekeerFormulierZetVerwachtingOpMisschien() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d H:i:s'),
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week1',
      'WERVING.mee_status'           => NULL,
      'WERVING.mee_verwachting'      => NULL,
    ]);

    $meeVerwachting = $result['WERVING.mee_verwachting'] ?? 'NIET_GEVONDEN';
    $this->assertSame('misschien', $meeVerwachting,
      'Eerste keer formulier invullen (datum dit fiscale jaar) → verwachting moet "misschien" zijn.');
  }

  /**
   * Recente belangstelling (na fiscalyear_start) + verwachting 'zekerniet' → reset naar 'misschien'
   *
   * Logica (sectie 3.3):
   *   date_biggerequal(datum, fiscalyear_start) == 1
   *   → datum NA fiscal jaar start → negatieve verwachting → reset naar 'misschien'
   *
   * 3 maanden geleden is altijd ná de fiscalyear_start van december vorig jaar.
   */
  public function testOudeBelangstellingZekernietResetNaarMisschien() {
    // 3 maanden geleden = binnen het huidige boekjaar (gestart in december)
    $datumOud = date('Y-m-d H:i:s', strtotime('-3 months'));

    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => $datumOud,
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week1',
      'WERVING.mee_verwachting'      => 'zekerniet',
    ]);

    $meeVerwachting = $result['WERVING.mee_verwachting'] ?? 'NIET_GEVONDEN';
    $this->assertSame('misschien', $meeVerwachting,
      'Oude belangstelling met verwachting "zekerniet" moet worden gereset naar "misschien".');
  }

  /**
   * Recente belangstelling + verwachting 'waarschijnlijktniet' → reset naar 'misschien'
   */
  public function testOudeBelangstellingWaarschijnlijktnietResetNaarMisschien() {
    $datumOud = date('Y-m-d H:i:s', strtotime('-3 months'));

    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => $datumOud,
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week1',
      'WERVING.mee_verwachting'      => 'waarschijnlijktniet',
    ]);

    $meeVerwachting = $result['WERVING.mee_verwachting'] ?? 'NIET_GEVONDEN';
    $this->assertSame('misschien', $meeVerwachting,
      'Oude verwachting "waarschijnlijktniet" moet worden gereset naar "misschien".');
  }

  /**
   * Recente belangstelling + lege verwachting → ook 'misschien'
   */
  public function testOudeBelangstellingLegeVerwachtingGeeftMisschien() {
    $datumOud = date('Y-m-d H:i:s', strtotime('-3 months'));

    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => $datumOud,
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week1',
      'WERVING.mee_verwachting'      => NULL,
    ]);

    $meeVerwachting = $result['WERVING.mee_verwachting'] ?? 'NIET_GEVONDEN';
    $this->assertSame('misschien', $meeVerwachting,
      'Oude belangstelling zonder verwachting moet ook "misschien" opleveren.');
  }

  /**
   * data_to_inject bevat altijd de verwachte Werving-sleutels
   */
  public function testRetourArrayBevatVerwachteSleutels() {
    $result = werving_civicrm_configure($this->contactId, 'hook', [
      'WERVING.Datum_belangstelling' => date('Y-m-d'),
      'WERVING.Welke_leeftijdsgroep' => 'kinderkamp',
      'WERVING.Welke_kampweek'       => 'week1',
    ]);

    $this->assertIsArray($result, 'werving_civicrm_configure() moet een array teruggeven.');
    foreach (['WERVING.Welke_kampweken', 'WERVING.mee_verwachting', 'WERVING.mee_status'] as $key) {
      $this->assertArrayHasKey($key, $result, "Sleutel '$key' ontbreekt in de retourarray.");
    }
  }
}
