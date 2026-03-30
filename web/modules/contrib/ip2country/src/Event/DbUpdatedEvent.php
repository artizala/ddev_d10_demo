<?php

declare(strict_types=1);

namespace Drupal\ip2country\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when the Ip2Country database is updated.
 */
class DbUpdatedEvent extends Event {

  const EVENT_NAME = 'ip2country.database_updated';

  /**
   * The registry used.
   *
   * @var string
   */
  protected $registry;

  /**
   * The number of database rows affected.
   *
   * @var int
   */
  protected $rows;

  /**
   * Constructs the object.
   *
   * @param string $registry
   *   The registry used.
   * @param int $rows
   *   The number of database rows affected.
   */
  public function __construct(string $registry, int $rows) {
    $this->registry = $registry;
    $this->rows = $rows;
  }

}
