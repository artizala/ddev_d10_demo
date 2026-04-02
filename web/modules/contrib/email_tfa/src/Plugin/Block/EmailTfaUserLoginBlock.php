<?php

namespace Drupal\email_tfa\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Plugin\Block\UserLoginBlock;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Email Tfa User login' block.
 *
 * @Block(
 *   id = "email_tfa_user_login_block",
 *   admin_label = @Translation("Email Tfa User login block"),
 *   category = @Translation("Forms")
 * )
 */
final class EmailTfaUserLoginBlock extends UserLoginBlock {

  /**
   * The config factory.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new UserLoginBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, RouteMatchInterface $route_match, FormBuilderInterface $form_builder, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match, $form_builder);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    $access = parent::blockAccess($account);
    $config = $this->configFactory->get('email_tfa.settings');
    $status = $config->get('status');
    $route_name = $this->routeMatch->getRouteName();
    $disabled_route = in_array($route_name, ['email_tfa.email_tfa_verify_login']);
    if ($access->isForbidden() || !$status || $disabled_route) {
      return AccessResult::forbidden();
    }
    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $form = \Drupal::formBuilder()->getForm('Drupal\email_tfa\Form\EmailTfaLoginForm');
    $form['#action'] = Url::fromRoute('<current>', [], [
      'query' => $this->getDestinationArray(),
      'external' => FALSE,
    ])->toString();
    // Build action links.
    $items = [];
    if ($this->configFactory->get('user.settings')->get('register') != UserInterface::REGISTER_ADMINISTRATORS_ONLY) {
      $items['create_account'] = Link::fromTextAndUrl($this->t('Create new account'), new Url('user.register', [], [
        'attributes' => [
          'title' => $this->t('Create a new user account.'),
          'class' => ['create-account-link'],
        ],
      ]));
    }
    $items['request_password'] = Link::fromTextAndUrl($this->t('Reset your password'), new Url('user.pass', [], [
      'attributes' => [
        'title' => $this->t('Send password reset instructions via email.'),
        'class' => ['request-password-link'],
      ],
    ]));
    return [
      'user_login_form' => $form,
      'user_links' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

}
