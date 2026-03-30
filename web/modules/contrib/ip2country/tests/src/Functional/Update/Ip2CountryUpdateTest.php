<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests that ip2country settings are properly updated during database updates.
 *
 * @group ip2country
 * @group legacy
 */
class Ip2CountryUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $d10_specific_dump = DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz';
    $d11_specific_dump = DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz';

    // Can't use the same dump in D10 and D11.
    if (file_exists($d10_specific_dump)) {
      $core_dump = $d10_specific_dump;
    }
    else {
      $core_dump = $d11_specific_dump;
    }

    // Use core fixture and Ip2Country-specific fixture.
    $this->databaseDumpFiles = [
      $core_dump,
      __DIR__ . '/../../../fixtures/update/drupal-8.ip2country-update-batch-size-2187895.php',
    ];
  }

  /**
   * Tests ip2country_update_8101().
   *
   * @see ip2country_update_8101()
   */
  public function testHookUpdate8101(): void {
    // Load the 'ip2country.settings' configuration settings, then check
    // that it does not contain the 'batch_size' setting before the update.
    $config = $this->config('ip2country.settings');
    $this->assertNull($config->get('batch_size'));

    // Run updates.
    $this->runUpdates();

    // Check that 'ip2country.settings' configuration setting 'batch_size'
    // has the default value of 200 after the update.
    $config = $this->config('ip2country.settings');
    $this->assertSame(200, $config->get('batch_size'));
  }

}
