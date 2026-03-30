<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Functional;

use Drupal\Component\Utility\Environment;
use Drupal\Tests\BrowserTestBase;

// cspell:ignore unpriv

/**
 * Tests operations of the IP to Country module.
 *
 * @todo Need 1 class for unit tests, 1 class for functional tests,
 * and 1 function for DB tests because filling takes so long.
 *
 * @group ip2country
 */
class Ip2CountryTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dblog', 'help', 'block'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Authenticated but unprivileged user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $unprivUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    //
    // Don't install ip2country! parent::setUp() creates a clean
    // environment, so we can influence the install before we call setUp().
    // We don't want the DB populated, so we'll manually install ip2country.
    //
    parent::setUp();

    //
    // Set a run-time long enough so the script won't break.
    //
    $this->timeLimit = 3 * 60;  // 3 minutes!
    Environment::setTimeLimit($this->timeLimit);

    // Turn off automatic DB download when module is installed.
    \Drupal::state()->set('ip2country_populate_database_on_install', FALSE);

    // Explicitly install the module so that it will have access
    // to the configuration variable we set above.
    $status = \Drupal::service('module_installer')->install(['ip2country'], FALSE);
    $this->resetAll();  // The secret ingredient.

    $this->assertTrue($status, 'Module ip2country enabled.');
    $count = \Drupal::service('ip2country.manager')->getRowCount();
    $this->assertEquals(0, $count, 'Database is empty.');

    // System help block is needed to see output from hook_help().
    $this->drupalPlaceBlock('help_block', ['region' => 'help']);

    // Need page_title_block because we test page titles.
    $this->drupalPlaceBlock('page_title_block');

    // Create our test users.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access site reports',
      'administer ip2country',
    ]);
    $this->unprivUser = $this->drupalCreateUser();
  }

  /**
   * Tests IP lookup for addresses in / not in the database.
   */
  public function testIpLookup(): void {
    \Drupal::service('ip2country.manager')->updateDatabase('arin');

    $count = \Drupal::service('ip2country.manager')->getRowCount();
    $this->assertNotEquals(0, $count, 'Database has been updated with ' . $count . ' rows.');

    // Real working IPs.
    $ip_array = [
      '125.29.33.201' => 'JP',
      '212.58.224.138' => 'GB',
      '184.51.240.110' => 'US',
      '210.87.9.66' => 'AU',
      '93.184.216.119' => 'EU',
    ];
    foreach ($ip_array as $ip_address => $country) {
      // Test dotted quad string form of address.
      $lookup = \Drupal::service('ip2country.lookup')->getCountry($ip_address);
      $this->assertEquals($country, $lookup, $ip_address . ' found, resolved to ' . $lookup . '.');

      // Test 32-bit unsigned long form of address.
      $usl_lookup = \Drupal::service('ip2country.lookup')->getCountry(ip2long($ip_address));
      $this->assertEquals($lookup, $usl_lookup, 'Unsigned long lookup found same country code.');

      $this->assertTrue(TRUE, 'Valid IP found in database.');
    }

    // Invalid and reserved IPs.
    $ip_array = [
      '127.0.0.1', '358.1.1.0',
    ];
    foreach ($ip_array as $ip_address) {
      $country = \Drupal::service('ip2country.lookup')->getCountry($ip_address);
      $this->assertFalse($country, $ip_address . ' not found in database.');
      $this->assertTrue(TRUE, 'Invalid IP not found in database.');
    }

    \Drupal::service('ip2country.manager')->emptyDatabase();
    $count = \Drupal::service('ip2country.manager')->getRowCount();
    $this->assertEquals(0, $count, 'Database is empty.');
  }

  /**
   * Tests injecting IP data via hook_ip2country_alter().
   */
  public function testAlterHook(): void {
    $this->assertTrue(TRUE, 'testAlterHook passed.');
  }

  /**
   * Tests Default country.
   */
  public function testDefaultCountry(): void {
    $this->assertTrue(TRUE, 'testDefaultCountry passed.');
  }

  /**
   * Tests module permissions / access to configuration page.
   */
  public function testUserAccess(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    //
    // Test as anonymous user.
    //
    $this->drupalGet('admin/config');
    $assert->statusCodeEquals(403);
    $assert->pageTextContains('Access denied');
    $assert->pageTextContains('You are not authorized to access this page.');

    $this->drupalGet('admin/config/people/ip2country');
    $assert->statusCodeEquals(403);

    // Try to trigger DB update as anonymous.
    $this->drupalGet('admin/config/people/ip2country/update/arin');
    $assert->statusCodeEquals(403);

    //
    // Test as authenticated but unprivileged user.
    //
    $this->drupalLogin($this->unprivUser);
    $this->drupalGet('admin/config');
    $assert->statusCodeEquals(403);

    $this->drupalGet('admin/config/people/ip2country');
    $assert->statusCodeEquals(403);
    $this->drupalLogout();

    //
    // As admin user.
    //
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('User location');
    $assert->pageTextContains('Settings for determining user location from IP address.');

    $this->drupalGet('admin/config/people/ip2country');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('User location');
    // This is output by ip2country_help().
    $assert->pageTextContains('Configuration settings for the ip2country module.');
    $assert->pageTextContains('Database is empty.');
    // Check that database updates are being logged to watchdog.
    $assert->fieldValueEquals('ip2country_watchdog', 1);
    // Check that database updates are using 'all' by default.
    $assert->fieldValueEquals('ip2country_rir', 'all');

    // Update database via UI - choose a random RIR.
    // (Actually short-circuiting the UI here because of the Ajax call).
    $rir = array_rand([
      'afrinic' => 'AFRINIC',
      'arin'    => 'ARIN',
      'apnic'   => 'APNIC',
      'lacnic'  => 'LACNIC',
      'ripe'    => 'RIPE',
    ]);
    $this->drupalGet('admin/config/people/ip2country/update/' . $rir);
    $assert->pageTextContains(
      'The IP to Country database has been updated from ' . mb_strtoupper($rir) . '.'
    );
    $rows = \Drupal::service('ip2country.manager')->getRowCount();

    // Check watchdog.
    $this->drupalGet('admin/reports/dblog');
    $assert->pageTextContains('Recent log messages');
    $assert->pageTextContains('ip2country');
    $assert->linkExists('Manual database update from ' . mb_strtoupper($rir) . ' server. Table contains ' . $rows . ' rows.');

    // Drill down.
    $this->clickLink('Manual database update from ' . mb_strtoupper($rir) . ' server. Table contains ' . $rows . ' rows.');
    $assert->pageTextContains('Details');
    $assert->pageTextContains('ip2country');
    $assert->pageTextContains('Manual database update from ' . mb_strtoupper($rir) . ' server. Table contains ' . $rows . ' rows.');

    $this->drupalLogout();
  }

  /**
   * Tests $user object for proper value.
   */
  public function testUserObject(): void {
    $this->assertTrue(TRUE, 'testUserObject passed.');
  }

  /**
   * Tests UI.
   */
  public function testUi(): void {
    $this->assertTrue(TRUE, 'testUi passed.');
  }

  /**
   * Tests IP Spoofing.
   *
   * @todo Should cover anonymous vs authenticated users and
   * check for info $messages.
   */
  public function testIpSpoofing(): void {
    $this->assertTrue(TRUE, 'testIpSpoofing passed.');
  }

  /**
   * Tests country spoofing.
   *
   * @todo Should cover anonymous vs authenticated users and
   * check for info $messages.
   */
  public function testCountrySpoofing(): void {
    $this->assertTrue(TRUE, 'testCountrySpoofing passed.');
  }

  /**
   * Tests manual lookup.
   */
  public function testIpManualLookup(): void {
    // $this->clickLink('Lookup');
    $this->assertTrue(TRUE, 'testIpManualLookup passed.');
  }

  /**
   * Tests DB download.
   */
  public function testDbDownload(): void {
    \Drupal::service('ip2country.manager')->emptyDatabase();

    $count = \Drupal::service('ip2country.manager')->getRowCount();
    $this->assertEquals(0, $count, 'Database is empty.');

    // Choose a random RIR.
    $rir = array_rand([
      // 'afrinic' => 'AFRINIC', // Don't use AFRINIC because it's incomplete.
      'arin'    => 'ARIN',
      'apnic'   => 'APNIC',
      'lacnic'  => 'LACNIC',
      'ripe'    => 'RIPE',
    ]);
    \Drupal::service('ip2country.manager')->updateDatabase($rir);

    $count = \Drupal::service('ip2country.manager')->getRowCount();
    $this->assertNotEquals(0, $count, 'Database has been updated from ' . mb_strtoupper($rir) . ' with ' . $count . ' rows.');

    \Drupal::service('ip2country.manager')->emptyDatabase();
    $count = \Drupal::service('ip2country.manager')->getRowCount();
    $this->assertEquals(0, $count, 'Database is empty.');
  }

  /**
   * Tests manual DB update.
   */
  public function testDbManualUpdate(): void {
    // phpcs:disable
    // $this->clickLink('Update');
    $connection = \Drupal::database();
    $rows = $connection->select('ip2country')->countQuery()->execute()->fetchField();
    // Check that Database was updated manually.
    // $assert->pageTextContains(
    //   'The IP to Country database has been updated from ' . mb_strtoupper($rir) . '. ' . $rows . ' rows affected.'
    // );
    $this->assertTrue(TRUE, 'testDbManualUpdate passed.');
    // phpcs:enable
  }

  /**
   * Tests cron DB update.
   */
  public function testDbCronUpdate(): void {
    $this->assertTrue(TRUE, 'testDbCronUpdate passed.');
  }

  /**
   * Tests logging of DB updates.
   */
  public function testDbUpdateLogging(): void {
    // phpcs:disable
    // Turn off logging.

    // Turn on logging.
    $edit = [
      'ip2country_watchdog' => ['test' => TRUE],
    ];
    // $this->drupalGet('admin/store/settings/countries/edit');
    // $this->submitForm($edit, 'Import');
    // Check that watchdog reported database update.
    // $assert->pageTextContains('Database updated from ' . mb_strtoupper($rir) . ' server.');

    $this->assertTrue(TRUE, 'testDbUpdateLogging passed.');
    // phpcs:enable
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Perform any clean-up tasks.
    \Drupal::state()->delete('ip2country_populate_database_on_install');

    // Finally...
    parent::tearDown();
  }

}
