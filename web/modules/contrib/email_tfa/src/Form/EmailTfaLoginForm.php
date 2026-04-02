<?php

namespace Drupal\email_tfa\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\user\Form\UserLoginForm;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a Email tfa form.
 */
class EmailTfaLoginForm extends UserLoginForm {

  /**
   * The user flood control service.
   *
   * @var \Drupal\user\UserFloodControl
   */
  protected $userFloodControl;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The user authentication object.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The bare HTML renderer.
   *
   * @var \Drupal\Core\Render\BareHtmlPageRendererInterface
   */
  protected $bareHtmlPageRenderer;

  /**
   * The route match object for the current page.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;


  /**
   * The email_tfa.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new subscriber after login.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\user\UserFloodControlInterface $user_flood_control
   *   The user flood control service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Render\BareHtmlPageRendererInterface $bare_html_renderer
   *   The bare HTML renderer.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              UserFloodControlInterface $user_flood_control,
                              UserStorageInterface $user_storage,
                              UserAuthInterface $user_auth,
                              RendererInterface $renderer,
                              BareHtmlPageRendererInterface $bare_html_renderer,
                              PrivateTempStoreFactory $temp_store_factory
  ) {
    parent::__construct($user_flood_control, $user_storage, $user_auth, $renderer, $bare_html_renderer);
    $this->config = $config_factory->get('email_tfa.settings');
    $this->tempStoreFactory = $temp_store_factory->get('email_tfa');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('user.flood_control'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('user.auth'),
      $container->get('renderer'),
      $container->get('bare_html_page_renderer'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uid = NULL, $hash = NULL) {
    $form = parent::buildForm($form, $form_state);
    $status = $this->config->get('status');
    if ($this->getRequest()->query->get('destination')) {
      // save the destination in the $form_state to be used on submit.
      $form_state->set('destination', $this->getRequest()->query->get('destination'));
      // remove the destination from the query string.
      $this->getRequest()->query->remove('destination');
    }
    $form['#cache'] = ['max-age' => 0];
    if (!$status) {
      return $form;
    }

    $form['#validate'][] = '::emailTfaLoginFormValidate';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->get('uid');
    if (empty($uid)) {
      return;
    }

    $status = $this->config->get('status');
    $user = $this->userStorage->load($uid);
    $destination = $form_state->get('destination');

    if (!$status || !$this->checkTwoFactorAuthentication($user)) {
      if ($destination) {
        $this->getRequest()->query->set('destination', $destination);
      }
      return parent::submitForm($form, $form_state);
    }

    $hash = _email_tfa_hash($uid, $user->getAccountName(), $user->getPassword());
    delete_temp_store($hash);
    $this->tempStoreFactory->set($hash . '_email_tfa_expire_time', time());
    $this->tempStoreFactory->set($hash . '_email_tfa_resend_mail', TRUE);
    if ($destination) {
      $form_state->setRedirect('email_tfa.email_tfa_verify_login',
        [
          'uid' => $form_state->get('uid'),
          'hash' => $hash,
          'destination' => $destination,
        ]
      );
    }
    else {
      $form_state->setRedirect('email_tfa.email_tfa_verify_login',
        [
          'uid' => $form_state->get('uid'),
          'hash' => $hash,
        ]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function emailTfaLoginFormValidate(array &$form, FormStateInterface $form_state) {
    $threshold = ($this->config->get('flood_threshold')) ? $this->config->get('flood_threshold') : 5;
    $window = ($this->config->get('flood_window')) ? $this->config->get('flood_window') : 3600;
    $identifier = $form_state->get('uid');
    if (!$this->userFloodControl->isAllowed('email_tfa.failed_validation', $threshold, $window, $identifier)) {
      $form_state->setErrorByName('', $this->t('There have been more than @threshold failed login attempts for this account. Please try again later.', ['@threshold' => $threshold]));
    }

  }

  /**
   * Check if the current user has two factor authentication enabled.
   *
   * @param \Drupal\user\Entity\User $user
   *  The user object.
   *
   * @return bool
   *   TRUE if the user must verify the email, FALSE otherwise.
    */
  private function checkTwoFactorAuthentication(\Drupal\user\Entity\User $user): bool {
    $tracks = $this->config->get('tracks');
    $user_one = $this->config->get('user_one');
    $ignore_role = $this->config->get('ignore_role');

    // If user one is exclude and its the current one.
    if ($user_one == 1 && $user->id() == 1 && $tracks == 'globally_enabled') {
      return FALSE;
    }
    // Globally enabled pathway && some roles if selected will excluded.
    elseif ($tracks == 'globally_enabled' && !_email_tfa_in_array_any($user->getRoles(), $ignore_role)) {
      // Check if user is NOT already verified.
      return TRUE;
    }
    elseif ($tracks == 'optionally_by_users') {
      if ($user->get('email_tfa_status')->value == 1) {
        return TRUE;
      }
    }

    return FALSE;
  }
}
