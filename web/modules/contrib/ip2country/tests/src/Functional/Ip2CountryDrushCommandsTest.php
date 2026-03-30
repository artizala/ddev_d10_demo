<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * @coversDefaultClass \Drupal\ip2country\Drush\Commands\Ip2CountryDrushCommands
 *
 * @group ip2country
 */
class Ip2CountryDrushCommandsTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ip2country'];

  /**
   * Tests the Drush ip2country:status command.
   */
  public function testStatusCommand(): void {
    // Checks the status of the database. In a test case, this will be loaded
    // during the test.
    $this->drush('ip2country:status');

    $update_time = \Drupal::service('state')->get('ip2country_last_update');
    $this->assertNotEmpty($update_time);
    $date = \Drupal::service('date.formatter')->format($update_time, 'ip2country_date');
    $time = \Drupal::service('date.formatter')->format($update_time, 'ip2country_time');
    $expected_output = "Database last updated on $date at $time from ALL server.";
    $this->assertEquals($expected_output, $this->getOutput());
  }

  /**
   * Tests the Drush ip2country:lookup command.
   */
  public function testLookupCommand(): void {
    // Looks up the IP address for www.drupal.org.
    // Output defaults to table format.
    $this->drush('ip2country:lookup', ['151.101.22.217']);

    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $expected_output =
      "---------------- --------------- -------------- \n" .
      "  IP address       Country         Country code  \n" .
      " ---------------- --------------- -------------- \n" .
      "  151.101.22.217   United States   US            \n" .
      " ---------------- --------------- --------------";
    // phpcs:enable Drupal.Strings.UnnecessaryStringConcat.Found
    $this->assertEquals($expected_output, $this->getOutput());

    // Looks up the IP address for www.drupal.org.
    // Specify YAML format output.
    $this->drush('ip2country:lookup', ['151.101.22.217'], ['format' => 'yaml']);

    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $expected_output =
      "151.101.22.217:\n" .
      "  ip_address: 151.101.22.217\n" .
      "  name: 'United States'\n" .
      "  country_code_iso2: US";
    // phpcs:enable Drupal.Strings.UnnecessaryStringConcat.Found
    $this->assertEquals($expected_output, $this->getOutput());
  }

  /**
   * Tests the Drush ip2country:update command.
   */
  public function testUpdateCommand(): void {
    // Updates the database from 'all' the registries then checks the results.
    $this->drush('ip2country:update', [], ['registry' => 'all']);

    $rows = \Drupal::service('ip2country.manager')->getRowCount();
    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $expected_output =
      "Updating ... Completed." . "\n" .
      "Database updated from ALL server. Table contains $rows rows.";
    // phpcs:enable Drupal.Strings.UnnecessaryStringConcat.Found
    $this->assertEquals($expected_output, $this->getOutput());
  }

}
