<?php

declare(strict_types=1);

namespace Drupal\ip2country\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations to create and dispatch User Events.
 */
final class Ip2CountryUserHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new Ip2CountryUserHooks service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current_user service.
   * @param \Drupal\user\UserDataInterface $userData
   *   The current user's data.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The ip2country.lookup service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MessengerInterface $messenger,
    protected AccountInterface $currentUser,
    protected UserDataInterface $userData,
    protected Ip2CountryLookupInterface $ip2countryLookup,
    protected RequestStack $requestStack,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Implements hook_user_login().
   *
   * Detects IP and determines country upon user login.
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $account): void {
    // Successful login. First determine user's country based on IP.
    $ip = $this->requestStack->getCurrentRequest()->getClientIp();
    $country_code = $this->ip2countryLookup->getCountry($ip);
    $ip2country_config = $this->configFactory->get('ip2country.settings');

    // Now check to see if this user has "administer ip2country" permission
    // and if debug mode set. If both are TRUE, use debug information
    // instead of real information.
    if ($this->currentUser->hasPermission('administer ip2country') &&
        $ip2country_config->get('debug')) {
      $type = $ip2country_config->get('test_type');
      // phpcs:disable Drupal.WhiteSpace.OpenBracketSpacing.OpeningWhitespace
      if ($type == 0) {  // Debug Country entered.
        $country_code = $ip2country_config->get('test_country');
      }
      else {  // Debug IP entered.
        $ip = $ip2country_config->get('test_ip_address');
        $country_code = $this->ip2countryLookup->getCountry($ip);
      }
      // phpcs:enable Drupal.WhiteSpace.OpenBracketSpacing.OpeningWhitespace
      $this->messenger->addMessage($this->t('Using DEBUG value for Country - @country', ['@country' => $country_code]));
    }

    // Finally, save country, if it has been determined.
    if ($country_code) {
      // Store the ISO country code in the user.data service object.
      $this->userData->set('ip2country', $account->id(), 'country_iso_code_2', $country_code);
    }
  }

  /**
   * Implements hook_user_load().
   *
   * Takes care of restoring country data from {users_data}.
   */
  #[Hook('user_load')]
  public function userLoad($accounts) {
    foreach ($accounts as $account) {
      $user_data = $this->userData->get('ip2country', $account->id(), 'country_iso_code_2');
      if (isset($user_data)) {
        $accounts[$account->id()]->country_iso_code_2 = $user_data;
      }
    }
  }

}
