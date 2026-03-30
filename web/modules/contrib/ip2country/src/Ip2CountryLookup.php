<?php

declare(strict_types=1);

namespace Drupal\ip2country;

use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The ip2country.lookup service.
 */
class Ip2CountryLookup implements Ip2CountryLookupInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs an Ip2CountryLookup object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(RequestStack $requestStack, Connection $connection) {
    $this->currentRequest = $requestStack->getCurrentRequest();
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountry($ip_address = NULL): string|false {
    $ip_address = $ip_address ?? $this->currentRequest->getClientIp();

    if (is_string($ip_address)) {
      $ipl = ip2long($ip_address);
    }
    else {
      $ipl = $ip_address;
    }

    // Locate IP within range.
    $sql = "SELECT country FROM {ip2country}
            WHERE (:start >= ip_range_first AND :end <= ip_range_last)";
    // Limit to 1 result.
    $result = $this->connection->queryRange($sql, 0, 1, [':start' => $ipl, ':end' => $ipl])->fetchField();
    return $result;
  }

}
