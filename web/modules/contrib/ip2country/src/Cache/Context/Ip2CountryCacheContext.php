<?php

declare(strict_types=1);

namespace Drupal\ip2country\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the Ip2CountryCacheContext service, for "per country" caching.
 *
 * Cache context ID: 'ip.country'.
 */
class Ip2CountryCacheContext extends RequestStackCacheContextBase implements CacheContextInterface {

  /**
   * The ip2country.lookup service.
   *
   * @var \Drupal\ip2country\Ip2CountryLookupInterface
   */
  protected $ip2countryLookup;

  /**
   * Constructs an Ip2CountryCacheContext.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The ip2country.lookup service.
   */
  public function __construct(RequestStack $request_stack, Ip2CountryLookupInterface $ip2countryLookup) {
    parent::__construct($request_stack);
    $this->ip2countryLookup = $ip2countryLookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Country based on IP address');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->ip2countryLookup->getCountry($this->requestStack->getCurrentRequest()->getClientIp());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
