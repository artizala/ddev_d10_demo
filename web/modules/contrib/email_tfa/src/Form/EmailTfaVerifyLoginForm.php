<?php

namespace Drupal\email_tfa\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\user\UserFloodControlInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Provides a Email tfa form.
 */
class EmailTfaVerifyLoginForm extends FormBase {

  use StringTranslationTrait;

  /**
   * The user flood control service.
   *
   * @var \Drupal\user\UserFloodControlInterface
   */
  protected UserFloodControlInterface $flood;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The email_tfa.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;


  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new subscriber after login.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger_factory.
   * @param \Drupal\user\UserFloodControlInterface $user_flood_control
   *   The user flood control service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *  The date formatter.
   */
  public function __construct(ConfigFactoryInterface $config_factory, PrivateTempStoreFactory $temp_store_factory, MessengerInterface $messenger, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory , UserFloodControlInterface $user_flood_control , DateFormatterInterface $date_formatter) {
    $this->config = $config_factory->get('email_tfa.settings');
    $this->tempStoreFactory = $temp_store_factory->get('email_tfa');
    $this->messenger = $messenger;
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->flood = $user_flood_control;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('tempstore.private'),
      $container->get('messenger'),
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('user.flood_control'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'email_tfa_email_tfa_verify_login';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uid = NULL, $hash = NULL) {
    $form['#cache']['max-age'] = 0;
    if ($this->getRequest()->query->get('destination')) {
      // save the destination in the $form_state to be used on submit.
      $form_state->set('destination', $this->getRequest()->query->get('destination'));
      // remove the destination from the query string.
      $this->getRequest()->query->remove('destination');
    }
    $timeouts = $this->config->get('timeouts');
    if (!empty($this->tempStoreFactory->get($hash . '_email_tfa_expire_time'))) {
      if (time() - (int) $this->tempStoreFactory->get($hash . '_email_tfa_expire_time') > $timeouts) {
        delete_temp_store($hash);
        $url = Url::fromRoute('user.page')->toString();
        $response = new RedirectResponse($url);
        $response->send();
        $this->messenger->addError($this->t('Two-factor Authentication is Expired.'));
        exit;
      }
    }

    if (!$this->tempStoreFactory->get($hash . '_email_tfa_send_mail')) {
      $this->sendEmail($uid, $hash);
    }

    // Fill the form with the user data.
    $form_state->set('uid', $uid);
    $form_state->set('hash', $hash);
    $form['verify'] = [
      '#type' => 'textfield',
      '#title' => $this->config->get('security_code_label_text'),
      '#description' => $this->config->get('security_code_description_text'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    // @todo add submit button for test mode if test mode is enabled.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->config->get('security_code_verify_text'),
      '#button_type' => 'primary',
      '#submit' => ['::loginSubmitForm'],
      '#validate' => ['::loginValidateForm'],
    ];

    if ($this->tempStoreFactory->get($hash . '_email_tfa_resend_mail')) {
      $form['resend'] = [
        '#type' => 'submit',
        '#value' => $this->config->get('security_code_resend_text'),
        '#name' => 'resend',
        '#button_type' => 'primary',
        // Skip validation.
        '#limit_validation_errors' => [],
        '#submit' => ['::sendEmailSubmitForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function loginValidateForm(array &$form, FormStateInterface $form_state) {
    $threshold = ($this->config->get('flood_threshold')) ? $this->config->get('flood_threshold') : 5;
    $window = ($this->config->get('flood_window')) ? $this->config->get('flood_window') : 3600;
    if ($form_state->getTriggeringElement()['#name'] != 'resend') {
      $hash = $form_state->get('hash');
      $identifier = $form_state->get('uid');
    if (!$this->flood->isAllowed('email_tfa.failed_validation', $threshold, $window, $identifier)) {
      $this->messenger->addError($this->t('There have been more than @threshold failed login attempts for this account. Please try again later.', ['@threshold' => $threshold]));
      delete_temp_store($hash);
      $response = new RedirectResponse(Url::fromRoute('user.page')->toString());
      $response->send();
      exit;
    }

      if (!hash_equals((string) $this->tempStoreFactory->get($hash . '_email_tfa_code'), (string) $form_state->getValue('verify'))) {
        $this->flood->register('email_tfa.failed_validation',$window, $identifier);
        $form_state->setErrorByName('verify', $this->config->get('verification_failed_message'));
      }

      $uid = $form_state->get('uid');
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!hash_equals((string) _email_tfa_hash($uid, $user->getAccountName(), $user->getPassword()), (string) $hash)) {
        $form_state->setErrorByName('verify', $this->config->get('not_authorized_message'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function loginSubmitForm(array &$form, FormStateInterface $form_state) {
    $hash = $form_state->get('hash');
    delete_temp_store($hash);
    $uid = $form_state->get('uid');
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    $this->flood->clear('email_tfa.failed_validation', $uid);
    $this->messenger->addStatus($this->config->get('verification_succeeded_message'));
    if (empty($form_state->get('destination'))) {
      $form_state->setRedirect(
        'entity.user.canonical',
        ['user' => $user->id()]
      );
    }
    else {
      $this->getRequest()->query->set('destination', $form_state->get('destination'));
    }

    if ($this->config->get('log_events')) {
      $replacements = [
        '@email' => $user->getEmail(),
        '@uid' => $user->id(),
      ];

      $this->loggerFactory->get('email_tfa')->info('user-email:@email, user-id:@uid has been logged in via email_tfa', $replacements);
    }

    user_login_finalize($user);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $uid = NULL, $hash = NULL) {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!hash_equals((string) _email_tfa_hash($uid, $user->getAccountName(), $user->getPassword()), (string) $hash)) {
      return AccessResult::forbidden();
    }

    if (empty($this->tempStoreFactory->get($hash . '_email_tfa_expire_time'))) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Generates a random number with a configured length.
   *
   * @return int
   *   A random number with the configured length.
   */
  protected function generateCode() {
    $length = $this->config->get('security_code_length');
    // Cast result of pow to int because it can return float.
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
    return random_int($min, $max);
  }

  /**
   * Resend email.
   */
  public function sendEmailSubmitForm(array &$form, FormStateInterface $form_state) {
    $hash = $form_state->get('hash');
    $uid = $form_state->get('uid');
    if ($this->tempStoreFactory->get($hash . '_email_tfa_resend_mail')) {
      $this->sendEmail($uid, $hash);
      $this->tempStoreFactory->set($hash . '_email_tfa_resend_mail', FALSE);
    }
    else {
      $this->messenger->addStatus($this->t('The email has already been sent successfully.'));
    }
    if ($form_state->get('destination')) {
      $form_state->setRedirect(
        'email_tfa.email_tfa_verify_login',
        [
          'uid' => $form_state->get('uid'),
          'hash' => $form_state->get('hash'),
          'destination' => $form_state->get('destination'),
        ]
      );
    }
  }

  /**
   * Sends an email to the user.
   */
  protected function sendEmail($uid, $hash) {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    $code = $this->generateCode();
    if ($this->config->get('dev_mode')) {
      $this->messenger->addStatus($this->t('Note: Test mode is enabled. The code is: @code.', ['@code' => $code]));
    }
    $this->tempStoreFactory->set($hash . '_email_tfa_send_mail', TRUE);
    $this->tempStoreFactory->set($hash . '_email_tfa_code', $code);
    $message = $this->mailManager->mail('email_tfa', 'send_email_tfa', $user->getEmail(), $this->languageManager->getDefaultLanguage()
      ->getId(), [
        'user' => $user,
        'email_tfa' => $this->tempStoreFactory->get($hash . '_email_tfa_code'),
      ]);

    if ($message['result'] && $this->config->get('log_events')) {
      $replacements = [
        '@email' => $user->getEmail(),
        '@uid' => $user->id(),
      ];

      $this->loggerFactory->get('email_tfa')->info('TFA email has been sent to user-email:@email, user-id:@uid', $replacements);
    }

    if ($message['result']) {
        $this->messenger->addStatus($this->t('Email sent successfully.'));
    }
  }

}
