<?php

declare(strict_types=1);

namespace Drupal\ip2country\Plugin\RulesAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rules\Context\ContextDefinition;
use Drupal\rules\Core\Attribute\RulesAction;
use Drupal\rules\Core\RulesActionBase;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Add a country_iso_code_2 to user data' action.
 *
 * @RulesAction(
 *   id = "ip2country_set_country",
 *   label = @Translation("Add country data to the user_data table"),
 *   category = @Translation("User"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User")
 *     ),
 *     "country_code" = @ContextDefinition("string",
 *       label = @Translation("Country")
 *     )
 *   }
 * )
 */
#[RulesAction(
  id: "ip2country_set_country",
  label: new TranslatableMarkup("Add country data to the user_data table"),
  category: new TranslatableMarkup("User"),
  context_definitions: [
    "user" => new ContextDefinition(
      data_type: "entity:user",
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user whose country should be set.")
    ),
    "country_code" => new ContextDefinition(
      data_type: "string",
      label: new TranslatableMarkup("Country"),
      description: new TranslatableMarkup("The 2-character ISO 3166 country code.")
    ),
  ]
)]
class SetUserCountry extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user.data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a SetUserCountry object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user.data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, UserDataInterface $userData) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->userData = $userData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('user.data')
    );
  }

  /**
   * Sets the country_iso_code_2 property of the global $user object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User object.
   * @param string $country_code
   *   A 2-character ISO 3166-2 country code.
   */
  protected function doExecute(AccountInterface $account, string $country_code): void {
    // Store the ISO country code in the $user object.
    $account = $this->entityTypeManager->getStorage('user')->load($account->id());
    $account->country_iso_code_2 = $country_code;
    $this->userData->set('ip2country', $account->id(), 'country_iso_code_2', $country_code);
  }

}
