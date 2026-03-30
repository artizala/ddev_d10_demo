<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\Tests\rest\Functional\CookieResourceTestTrait;
use Drupal\Tests\rest\Functional\ResourceTestBase;

/**
 * Tests the Ip2Country REST resource.
 *
 * @group ip2country
 */
class Ip2CountryResourceTest extends ResourceTestBase {
  use CookieResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'cookie';

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'ip_lookup';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['rest', 'ip2country'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $auth = isset(static::$auth) ? [static::$auth] : [];
    $this->provisionResource([static::$format], $auth);
  }

  /**
   * Makes REST API requests to lookup known-good IP addresses.
   */
  public function testIpLookup(): void {
    // Real working IPs that are in the database.
    $ip_array = [
      '125.29.33.201' => 'JP',
      '212.58.224.138' => 'GB',
      '184.51.240.110' => 'US',
      '210.87.9.66' => 'AU',
      '158.67.0.0' => 'EU',
    ];

    // Test authentication.
    $this->initAuthentication();
    $url = Url::fromRoute('rest.ip_lookup.GET', [
      'ip_address' => '127.0.0.1',
      '_format' => static::$format,
    ]);
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(
      403,
      "The 'restful get ip_lookup' permission is required.",
      $response,
      ['4xx-response', 'http_response'],
      ['user.permissions']
    );

    // Create a user account that has the required permissions to read
    // the ip_lookup resource via the REST API.
    $this->setUpAuthorization('GET');

    $expected_cache_tags = ['config:rest.resource.ip_lookup', 'http_response'];

    // Make requests for known-good IP addresses and verify results.
    foreach ($ip_array as $ip => $country) {
      $url->setRouteParameter('ip_address', $ip);
      $response = $this->request('GET', $url, $request_options);
      $this->assertResourceResponse(
        200,
        FALSE,
        $response,
        $expected_cache_tags,
        ['user.permissions'],
        FALSE,
        'MISS'
      );
      $lookup_result = Json::decode((string) $response->getBody());
      $this->assertEquals($country, $lookup_result, 'Country code is correct.');
    }

    // Request an unknown IP address.
    // We use a reserved "example" IP address as defined by RFC 5737.
    $url->setRouteParameter('ip_address', '203.0.113.0');
    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(404, 'IP Address 203.0.113.0 is not assigned to a country.', $response);

    // Make a bad request.
    $url->setRouteParameter('ip_address', 0);
    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(400, 'The IP address you entered is invalid. Please enter an address in the form xxx.xxx.xxx.xxx where xxx is between 0 and 255 inclusive.', $response);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['restful get ip_lookup']);
        break;

      default:
        throw new \UnexpectedValueException();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options) {}

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    return (new CacheableMetadata())
      ->setCacheTags(['4xx-response', 'http_response'])
      ->setCacheContexts(['user.permissions']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return "The 'restful get ip_lookup' permission is required.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

}
