<?php

namespace Civi\Werving;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test voor werving_get_field_map() in nl.onvergetelijk.werving.
 *
 * @group e2e
 *
 * werving_get_field_map() koppelt database-kolomnamen aan WERVING.*-sleutels.
 * Bevat leeftijdsberekeningen, belangstellingsdatum, kampweek-voorkeuren en
 * mee-update informatie. Geen DB-afhankelijkheid — pure array-logica.
 *
 * Scenario's:
 *   - Retourneert een non-lege array
 *   - Alle sleutels bevatten een numeriek suffix
 *   - Alle waarden beginnen met 'WERVING.'
 *   - Bevat leeftijdsvelden: leeftijd_decimalen, nextkamp_decimalen
 *   - Bevat mee-update velden: mee_update, mee_verwachting, mee_status
 *   - Bevat datum_belangstelling
 *   - Alle waarden zijn uniek
 */
class WervingFieldMapTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('werving_get_field_map')) {
      $this->markTestSkipped('werving_get_field_map() niet beschikbaar; is nl.onvergetelijk.werving geïnstalleerd?');
    }
  }

  public function testMapIsNonLeegArray() {
    $result = werving_get_field_map();
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
  }

  public function testSleutelsHebbenNumeriekeId() {
    foreach (werving_get_field_map() as $key => $value) {
      $this->assertMatchesRegularExpression('/_\d+$/', $key, "Sleutel '$key' moet eindigen op numeriek suffix.");
    }
  }

  public function testWaardenBeginnenMetWerving() {
    foreach (werving_get_field_map() as $key => $value) {
      $this->assertStringStartsWith('WERVING.', $value, "Waarde '$value' moet beginnen met 'WERVING.'.");
    }
  }

  public function testBevatLeeftijdsvelden() {
    $values = array_values(werving_get_field_map());
    $this->assertContains('WERVING.leeftijd_decimalen',  $values, 'WERVING.leeftijd_decimalen moet aanwezig zijn.');
    $this->assertContains('WERVING.nextkamp_decimalen',  $values, 'WERVING.nextkamp_decimalen moet aanwezig zijn.');
    $this->assertContains('WERVING.nextkamp_rondjaren',  $values, 'WERVING.nextkamp_rondjaren moet aanwezig zijn.');
  }

  public function testBevatMeeUpdateVelden() {
    $values = array_values(werving_get_field_map());
    $this->assertContains('WERVING.mee_update',      $values, 'WERVING.mee_update moet aanwezig zijn.');
    $this->assertContains('WERVING.mee_verwachting', $values, 'WERVING.mee_verwachting moet aanwezig zijn.');
    $this->assertContains('WERVING.mee_status',      $values, 'WERVING.mee_status moet aanwezig zijn.');
  }

  public function testBevatDatumBelangstelling() {
    $values = array_values(werving_get_field_map());
    $this->assertContains('WERVING.Datum_belangstelling', $values, 'WERVING.Datum_belangstelling moet aanwezig zijn.');
  }

  public function testWaardenZijnUniek() {
    $values = array_values(werving_get_field_map());
    $this->assertEquals(count($values), count(array_unique($values)));
  }
}
