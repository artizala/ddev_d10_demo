<?php

declare(strict_types=1);

namespace Drupal\ip2country\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for looking up IP addresses.
 *
 * @RestResource(
 *   id = "ip_lookup",
 *   label = @Translation("IP country lookup"),
 *   uri_paths = {
 *     "canonical" = "/ip2country/{ip_address}"
 *   }
 * )
 */
#[RestResource(
  id: "ip_lookup",
  label: new TranslatableMarkup("IP country lookup"),
  uri_paths: [
    "canonical" => "/ip2country/{ip_address}",
  ]
)]
class Ip2CountryResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * The ip2country.lookup service.
   *
   * @var \Drupal\ip2country\Ip2CountryLookupInterface
   */
  protected $ip2countryLookup;

  /**
   * Constructs a new Ip2CountryResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The Ip2Country lookup service manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Ip2CountryLookupInterface $ip2countryLookup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->ip2countryLookup = $ip2countryLookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('ip2country.lookup')
    );
  }

  /**
   * Responds to GET requests for this Resource.
   *
   * @param string $ip_address
   *   The IP address to look up, formatted as a dotted quad (xxx.xxx.xxx.xxx).
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the 2-character ISO 3166-2 country code for
   *   the given IP address.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the IP address is not assigned to a country.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the given IP address is not valid.
   */
  public function get(?string $ip_address = NULL): ResourceResponse {
    if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
      $country_code = $this->ip2countryLookup->getCountry($ip_address);

      if ($country_code) {
        return new ResourceResponse($country_code);
      }

      throw new NotFoundHttpException("IP Address $ip_address is not assigned to a country.");
    }

    throw new BadRequestHttpException('The IP address you entered is invalid. Please enter an address in the form xxx.xxx.xxx.xxx where xxx is between 0 and 255 inclusive.');
  }

}
