<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Unit\Integration\Condition;

use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Tests\rules\Unit\Integration\RulesEntityIntegrationTestBase;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\ip2country\Plugin\Condition\UserInCountry
 * @group RulesCondition
 */
class UserInCountryTest extends RulesEntityIntegrationTestBase {

  /**
   * The condition that is being tested.
   *
   * @var \Drupal\rules\Core\RulesConditionInterface
   */
  protected $condition;

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
   * The core user.data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $request;

  /**
   * The Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // We need the user module.
    $this->enableModule('user');
    // Must enable our module to make our plugins discoverable.
    $this->enableModule('ip2country');

    $user = $this->prophesize(UserInterface::class);
    $user->id()->willReturn(3);
    $this->container->set('current_user', $user->reveal());

    $this->userData = $this->prophesize(UserDataInterface::class);
    $this->container->set('user.data', $this->userData->reveal());

    // Setup a mock service to return the enabled countries which this
    // condition will compare against.
    $this->countryManager = $this->prophesize(CountryManagerInterface::class);
    $this->countryManager->getList()->willReturn(['BE', 'CA', 'AU', 'JP', 'ES']);
    $this->container->set('country_manager', $this->countryManager->reveal());

    $this->ip2countryLookup = $this->prophesize(Ip2CountryLookupInterface::class);
    $this->container->set('ip2country.lookup', $this->ip2countryLookup->reveal());

    // Mock a request.
    $this->request = $this->prophesize(Request::class);

    // Mock the request_stack service, make it return our mocked request,
    // and register it in the container.
    $this->requestStack = $this->prophesize(RequestStack::class);
    $this->requestStack->getCurrentRequest()->willReturn($this->request->reveal());
    $this->container->set('request_stack', $this->requestStack->reveal());

    $this->condition = $this->conditionManager->createInstance('ip2country_user_country');
  }

  /**
   * Tests the summary.
   *
   * @covers ::summary
   */
  public function testSummary(): void {
    $this->assertEquals('User IP is in Country', $this->condition->summary());
  }

  /**
   * Tests execute() method.
   *
   * @dataProvider ipProvider
   *
   * @covers ::evaluate
   */
  public function testConditionEvaluation(?string $user_country, string $ip, string $country, bool $result, bool $expects): void {
    $this->userData->get('ip2country', 3, 'country_iso_code_2')->willReturn($user_country);
    $this->userData->get('ip2country', 3, 'country_iso_code_2')->shouldBeCalledTimes(1);
    $this->request->getClientIp()->willReturn($ip);
    $this->request->getClientIp()->shouldBeCalledTimes($expects ? 1 : 0);
    $this->ip2countryLookup->getCountry($ip)->willReturn($country);
    $this->ip2countryLookup->getCountry($ip)->shouldBeCalledTimes($expects ? 1 : 0);

    $this->condition->setContextValue('countries', ['AU', 'CA', 'ES']);

    $this->assertEquals($result, $this->condition->evaluate());
  }

  /**
   * Provides data for ::testConditionEvaluation.
   *
   * Each element provides:
   *   user_country: The country code stored in the user data.
   *   ip: The IP address obtained from the request.
   *   country: The country code determined from the IP.
   *   result: Boolean result of the condition evaluation.
   *   expects: if we expect the ip2country.lookup service to be used.
   */
  public static function ipProvider(): array {
    return [
      [NULL, '192.0.2.0', 'BE', FALSE, TRUE],
      [NULL, '2002:0:0:0:0:0:c000:200', 'BE', FALSE, TRUE],
      [NULL, '192.0.2.0', 'CA', TRUE, TRUE],
      [NULL, '2002:0:0:0:0:0:c000:200', 'CA', TRUE, TRUE],
      ['AU', '192.0.2.0', 'BE', TRUE, FALSE],
      ['AU', '2002:0:0:0:0:0:c000:200', 'BE', TRUE, FALSE],
      ['BE', '192.0.2.0', 'ES', FALSE, FALSE],
      ['BE', '2002:0:0:0:0:0:c000:200', 'ES', FALSE, FALSE],
    ];
  }

}
