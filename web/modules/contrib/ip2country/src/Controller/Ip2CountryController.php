<?php

declare(strict_types=1);

namespace Drupal\ip2country\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Drupal\ip2country\Ip2CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller routines for user routes.
 */
class Ip2CountryController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The core country_manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The ip2country.lookup service.
   *
   * @var \Drupal\ip2country\Ip2CountryLookupInterface
   */
  protected $ip2countryLookup;

  /**
   * The ip2country.manager service.
   *
   * @var \Drupal\ip2country\Ip2CountryManagerInterface
   */
  protected $ip2countryManager;

  /**
   * Constructs an Ip2CountryController.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory service.
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The core country_manager service.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The ip2country.lookup service.
   * @param \Drupal\ip2country\Ip2CountryManagerInterface $ip2countryManager
   *   The ip2country.manager service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, CountryManagerInterface $countryManager, Ip2CountryLookupInterface $ip2countryLookup, Ip2CountryManagerInterface $ip2countryManager) {
    $this->logger = $logger;
    $this->countryManager = $countryManager;
    $this->ip2countryLookup = $ip2countryLookup;
    $this->ip2countryManager = $ip2countryManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('country_manager'),
      $container->get('ip2country.lookup'),
      $container->get('ip2country.manager')
    );
  }

  /**
   * AJAX callback to update the IP to Country database.
   *
   * @param string $rir
   *   String with name of IP registry. One of 'afrinic', 'arin', 'lacnic',
   *   'ripe'. Not case sensitive.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response for display by jQuery script.
   */
  public function updateDatabaseAction($rir): JsonResponse {
    $ip2country_config = $this->config('ip2country.settings');
    $watchdog = $ip2country_config->get('watchdog');

    $md5_checksum = $ip2country_config->get('md5_checksum');
    $batch_size = $ip2country_config->get('batch_size');

    // Update DB from RIR.
    $status = $this->ip2countryManager->updateDatabase($rir, $md5_checksum, $batch_size);

    if ($status != FALSE) {
      if ($watchdog) {
        $this->logger->get('ip2country')->notice('Manual database update from @registry server. Table contains @rows rows.', [
          '@registry' => mb_strtoupper($rir),
          '@rows' => $this->ip2countryManager->getRowCount(),
        ]);
      }
      return new JsonResponse([
        'count' => $this->t('@rows rows affected.', [
          '@rows' => $this->ip2countryManager->getRowCount(),
        ]),
        'server' => $rir,
        'message' => $this->t('The IP to Country database has been updated from @server.', [
          '@server' => mb_strtoupper($rir),
        ]),
      ]);
    }
    else {
      if ($watchdog) {
        $this->logger->get('ip2country')->notice('Manual database update from @registry server FAILED.', [
          '@registry' => mb_strtoupper($rir),
        ]);
      }
      return new JsonResponse([
        'count' => $this->t('@rows rows affected.', ['@rows' => 0]),
        'server' => $rir,
        'message' => $this->t('The IP to Country database update failed.'),
      ]);
    }
    exit();
  }

  /**
   * AJAX callback to lookup an IP address in the database.
   *
   * @param string $ip_address
   *   String with IP address.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response for display by jQuery script.
   */
  public function lookupAction($ip_address): JsonResponse {

    // Return results of manual lookup.
    $country_code = $this->ip2countryLookup->getCountry($ip_address);
    if ($country_code) {
      $country_list = $this->countryManager->getList();
      $country_name = $country_list[$country_code];
      return new JsonResponse([
        'message' => $this->t('IP Address @ip is assigned to @country (@code).', [
          '@ip' => $ip_address,
          '@country' => $country_name,
          '@code' => $country_code,
        ]),
      ]);
    }
    else {
      return new JsonResponse([
        'message' => $this->t('IP Address @ip is not assigned to a country.', [
          '@ip' => $ip_address,
        ]),
      ]);
    }
    exit();
  }

}
