<?php

declare(strict_types=1);

namespace Drupal\ip2country\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ip2country\Ip2CountryManagerInterface;

/**
 * Hook implementations used for scheduled execution.
 */
final class Ip2CountryCronHooks {

  /**
   * Constructs a new Ip2CountryCronHooks service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger.factory service.
   * @param \Drupal\ip2country\Ip2CountryManagerInterface $ip2countryManager
   *   The ip2country.manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $datetime
   *   The datetime.time service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected Ip2CountryManagerInterface $ip2countryManager,
    protected TimeInterface $datetime,
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_cron().
   *
   * Updates the IP to Country database automatically on a periodic
   * basis. Default period is 1 week.
   */
  #[Hook('cron')]
  public function cron(): void {
    $ip2country_config = $this->configFactory->get('ip2country.settings');
    $rir = $ip2country_config->get('rir');
    $md5_checksum = $ip2country_config->get('md5_checksum');
    $batch_size = $ip2country_config->get('batch_size');

    // Automatic database updates are disabled when $update_interval == 0.
    $update_interval = $ip2country_config->get('update_interval');
    if ($update_interval && $this->state->get('ip2country_last_update') <=
        $this->datetime->getRequestTime() - $update_interval) {
      $status = $this->ip2countryManager->updateDatabase($rir, $md5_checksum, $batch_size);
      // Log to watchdog if requested.
      if ($ip2country_config->get('watchdog')) {
        if ($status != FALSE) {
          // Success.
          $this->loggerFactory->get('ip2country')->notice('Database updated from @registry server. Table contains @rows rows.', [
            '@registry' => mb_strtoupper($ip2country_config->get('rir')),
            '@rows' => $status,
          ]);
        }
        else {
          // Failure.
          $this->loggerFactory->get('ip2country')->warning('Database update from @registry server FAILED.', [
            '@registry' => mb_strtoupper($ip2country_config->get('rir')),
          ]);
        }
      }
    }
  }

}
