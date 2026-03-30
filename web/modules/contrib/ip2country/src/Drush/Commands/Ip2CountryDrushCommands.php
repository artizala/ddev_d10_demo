<?php

declare(strict_types=1);

namespace Drupal\ip2country\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Drupal\ip2country\Ip2CountryManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush 12+ commands for the Ip2Country module.
 */
final class Ip2CountryDrushCommands extends DrushCommands {
  use AutowireTrait;

  /**
   * The logger for the ip2country channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $ip2countryLogger;

  /**
   * Ip2CountryDrushCommands constructor.
   *
   * @param \Drupal\Core\State\StateInterface $stateService
   *   The state service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The core country_manager service.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The ip2country lookup service.
   * @param \Drupal\ip2country\Ip2CountryManagerInterface $ip2countryManager
   *   The ip2country database manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger.factory service.
   */
  public function __construct(
    protected StateInterface $stateService,
    protected DateFormatterInterface $dateFormatter,
    protected ConfigFactoryInterface $configFactory,
    protected CountryManagerInterface $countryManager,
    protected Ip2CountryLookupInterface $ip2countryLookup,
    protected Ip2CountryManagerInterface $ip2countryManager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    parent::__construct();
    $this->ip2countryLogger = $logger_factory->get('ip2country');
  }

  /**
   * Updates the Ip2Country database from a Regional Internet Registry.
   *
   * @param array $options
   *   An array of optional parameters used to update the database. This array
   *   may contain the following keys:
   *   - registry: The RIR. One of 'all', 'afrinic', 'apnic', 'arin', 'lacnic',
   *     or 'ripe'.
   *   - batch_size: Database table row insertion size.
   *   - checksum: Download verified with checksum if this option is present.
   *
   * @command ip2country:update
   * @aliases ip-update,ip2country-update
   *
   * @option registry
   *   Regional Internet Registry from which to get the data.
   *   Allowed values are 'all' (default), 'afrinic', 'apnic', 'arin', 'lacnic',
   *   or 'ripe'. If this is any other string value then all five registries
   *   will be contacted, and each will provide its own data. The default and
   *   preferred option is 'all'.
   * @option batch_size
   *   Row insertion batch size. Defaults to '200' rows per insert.
   * @option checksum
   *   Validate data integrity with the checksum.
   *
   * @usage drush ip2country:update --registry=ripe
   *   Updates Ip2Country database of ip/country associations.
   * @usage drush ip2country:update --registry=apnic --batch_size=200 --checksum
   *   Updates Ip2Country database with a batch size of 200 rows and verifies
   *   the updated data with the checksum.
   *
   * @validate-module-enabled ip2country
   */
  #[CLI\Command(name: 'ip2country:update', aliases: ['ip-update', 'ip2country-update'])]
  #[CLI\Help(description: 'Updates the Ip2Country database from a Regional Internet Registry.')]
  #[CLI\Option(name: 'registry', description: "Regional Internet Registry from which to get the data. Allowed values are 'all' (default), 'afrinic', 'apnic', 'arin', 'lacnic', or 'ripe'. If this is any other string value then all five registries will be contacted, and each will provide its own data. The default and preferred option is 'all'.")]
  #[CLI\Option(name: 'batch-size', description: "Row insertion batch size. Defaults to '200' rows per insert.")]
  #[CLI\Option(name: 'checksum', description: 'Validate data integrity with the checksum.')]
  #[CLI\Usage(name: 'drush ip2country:update --registry=ripe', description: 'Updates Ip2Country database of ip/country associations.')]
  #[CLI\Usage(name: 'drush ip2country:update --registry=apnic --batch_size=200 --checksum', description: 'Updates Ip2Country database with a batch size of 200 rows and verifies the updated data with the checksum.')]
  #[CLI\ValidateModulesEnabled(modules: ['ip2country'])]
  public function update(array $options = ['registry' => self::REQ, 'batch_size' => 200, 'checksum' => FALSE]): void {
    $ip2country_config = $this->configFactory->get('ip2country.settings');
    $watchdog = $ip2country_config->get('watchdog');

    // If an option isn't specified, use the value from the module settings.
    if (empty($options['registry'])) {
      $options['registry'] = $ip2country_config->get('rir');
    }
    if (empty($options['checksum'])) {
      $options['checksum'] = $ip2country_config->get('md5_checksum');
    }
    if (empty($options['batch_size'])) {
      $options['batch_size'] = $ip2country_config->get('batch_size');
    }

    // Tell the user we're working on it ...
    $this->output->write(dt('Updating ... '), FALSE);

    $status = $this->ip2countryManager->updateDatabase(
      (string) $options['registry'],
      (bool) $options['checksum'],
      (int) $options['batch_size']
    );

    if ($status != FALSE) {
      $this->output->writeln(dt('Completed.'));
      $this->output->writeln(dt('Database updated from @registry server. Table contains @rows rows.', [
        '@registry' => mb_strtoupper($options['registry']),
        '@rows' => $status,
      ]));

      // Log update to watchdog, if ip2country logging is enabled.
      if ($watchdog) {
        $this->ip2countryLogger->notice('Drush-initiated database update from @registry server. Table contains @rows rows.', [
          '@registry' => mb_strtoupper($options['registry']),
          '@rows' => $status,
        ]);
      }
    }
    else {
      $this->output->writeln(dt('Failed.'));
      $this->output->writeln(dt('Database update from @registry server FAILED.', [
        '@registry' => mb_strtoupper($options['registry']),
      ]));
      // Log update failure to watchdog, if ip2country logging is enabled.
      if ($watchdog) {
        $this->ip2countryLogger->warning('Drush-initiated database update from @registry server FAILED.', [
          '@registry' => mb_strtoupper($options['registry']),
        ]);
      }
    }
  }

  /**
   * Finds the country associated with the given IP address.
   *
   * @param string $ip_address
   *   The IPV4 address to look up, in dotted-quad notation (e.g. 127.0.0.1).
   * @param array $options
   *   An array of optional parameters. Used for output formatting.
   *
   * @command ip2country:lookup
   * @aliases ip-lookup,ip2country-lookup
   *
   * @usage drush ip2country:lookup ip_address
   *   Returns a country code associated with the given IP address.
   * @usage drush ip2country:lookup ip_address --field=name
   *   Returns country name for the IP address.
   *
   * @table-style default
   * @field-labels
   *   ip_address: IP address
   *   name: Country
   *   country_code_iso2: Country code
   * @default-fields ip_address,name,country_code_iso2
   *
   * @validate-module-enabled ip2country
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Returns the country name and two-character ISO 3166 country code
   *   associated with the given IP address.
   */
  #[CLI\Command(name: 'ip2country:lookup', aliases: ['ip-lookup', 'ip2country-lookup'])]
  #[CLI\Help(description: 'Finds the country associated with the given IP address.')]
  #[CLI\Argument(name: 'ip_address', description: 'The IPV4 address to look up, in dotted-quad notation (e.g. 127.0.0.1).')]
  #[CLI\Usage(name: 'drush ip2country:lookup ip_address', description: 'Returns a country code associated with the given IP address.')]
  #[CLI\Usage(name: 'drush ip2country:lookup ip_address --field=name', description: 'Returns country name for the IP address.')]
  #[CLI\FieldLabels(labels: ['ip_address' => 'IP address', 'name' => 'Country', 'country_code_iso2' => 'Country code'])]
  #[CLI\DefaultFields(fields: ['ip_address', 'name', 'country_code_iso2'])]
  #[CLI\ValidateModulesEnabled(modules: ['ip2country'])]
  public function lookup(string $ip_address, array $options = ['format' => 'table']): RowsOfFields {
    $country_code = $this->ip2countryLookup->getCountry($ip_address);

    $rows = [];
    if ($country_code == FALSE) {
      $this->output->writeln(dt('IP address not found in the database.'));
    }
    else {
      $country_list = $this->countryManager->getList();
      $country_name = $country_list[$country_code];
      $rows[$ip_address] = [
        'ip_address' => $ip_address,
        'name' => (string) dt($country_name),
        'country_code_iso2' => $country_code,
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Displays the time and RIR of the last database update.
   *
   * @command ip2country:status
   * @aliases ip-status,ip2country-status
   *
   * @usage drush ip2country:status
   *   Returns a country code associated with the given IP address.
   *
   * @validate-module-enabled ip2country
   */
  #[CLI\Command(name: 'ip2country:status', aliases: ['ip-status', 'ip2country-status'])]
  #[CLI\Help(description: 'Displays the time and RIR of the last database update.')]
  #[CLI\Usage(name: 'drush ip2country:status', description: 'Returns a country code associated with the given IP address.')]
  #[CLI\ValidateModulesEnabled(modules: ['ip2country'])]
  public function status(): void {
    $update_time = $this->stateService->get('ip2country_last_update');
    if (!empty($update_time)) {
      $message = dt('Database last updated on @date at @time from @registry server.', [
        '@date' => $this->dateFormatter->format($update_time, 'ip2country_date'),
        '@time' => $this->dateFormatter->format($update_time, 'ip2country_time'),
        '@registry' => mb_strtoupper($this->stateService->get('ip2country_last_update_rir')),
      ]);
    }
    else {
      $message = dt('Database is empty.');
    }

    $this->output->writeln($message);
  }

}
