<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Unit\Integration\Event;

use Drupal\Tests\rules\Unit\Integration\Event\EventTestBase;
use Drupal\rules\Core\RulesEventManager;

/**
 * Checks that the event "ip2country.database_updated" is correctly defined.
 *
 * @coversDefaultClass \Drupal\ip2country\Event\DbUpdatedEvent
 *
 * @group ip2country
 */
class DbUpdatedEventTest extends EventTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Must enable our module to make our plugins discoverable.
    $this->enableModule('ip2country');

    // Tell the plugin manager where to look for plugins.
    $this->moduleHandler->getModuleDirectories()
      ->willReturn(['ip2country' => __DIR__ . '/../../../../../']);

    // Create a real plugin manager with a mock moduleHandler.
    $this->eventManager = new RulesEventManager($this->moduleHandler->reveal(), $this->entityTypeBundleInfo->reveal());
  }

  /**
   * Tests the event metadata.
   */
  public function testDbUpdatedEvent(): void {
    // Verify our event is discoverable.
    $plugin_definition = $this->eventManager->getDefinition('ip2country.database_updated');
    $this->assertSame('Ip2Country database updated', (string) $plugin_definition['label']);

    // Create event instance.
    $event = $this->eventManager->createInstance('ip2country.database_updated');

    // Verify event context values.
    $registry_context_definition = $event->getContextDefinition('registry');
    $this->assertSame('string', $registry_context_definition->getDataType());
    $this->assertSame('The registry used', $registry_context_definition->getLabel());

    $rows_context_definition = $event->getContextDefinition('rows');
    $this->assertSame('integer', $rows_context_definition->getDataType());
    $this->assertSame('Number of database rows affected', $rows_context_definition->getLabel());
  }

}
