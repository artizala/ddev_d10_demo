<?php

declare(strict_types=1);

namespace Drupal\ip2country\TypedData\Options;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Options provider to return all fields in the system.
 *
 * Provides country options as an array of ISO 3166 2-character country codes
 * keyed by country name.
 */
class CountryListOptions implements OptionsProviderInterface, ContainerInjectionInterface {

  /**
   * The core country_manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * Constructs a CountryListOptions object.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The core country_manager service.
   */
  public function __construct(CountryManagerInterface $country_manager) {
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    return $this->countryManager->getList();
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    // Flatten options firstly, because Possible Options may contain group
    // arrays.
    $flatten_options = OptGroup::flattenOptions($this->getPossibleOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    return $this->getPossibleValues();
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    return $this->getPossibleOptions();
  }

}
