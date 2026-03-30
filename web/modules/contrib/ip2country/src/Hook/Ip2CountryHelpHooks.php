<?php

declare(strict_types=1);

namespace Drupal\ip2country\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Hook implementations used to provide help.
 */
final class Ip2CountryHelpHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new Ip2CountryHelpHooks service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.ip2country':
        // @todo Replace this with a controller method.
        // @see https://www.drupal.org/node/2669988
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>';
        $output .= $this->t('Determines the Country where the user is located based on the IP address used.');
        $output .= '</p>';
        return $output;

      case 'ip2country.settings':
        $output = $this->t('Configuration settings for the ip2country module.');
        return (string) $output;
    }
    return NULL;
  }

}
