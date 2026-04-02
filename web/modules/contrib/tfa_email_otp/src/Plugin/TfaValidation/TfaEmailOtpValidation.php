<?php

namespace Drupal\tfa_email_otp\Plugin\TfaValidation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Exception\EncryptException;
use Drupal\encrypt\Exception\EncryptionMethodCanNotDecryptException;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\tfa\TfaRandomTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Email OTP validation class for performing Email OTP validation.
 *
 * @TfaValidation(
 *   id = "tfa_email_otp",
 *   label = @Translation("TFA Email one-time password (EOTP)"),
 *   description = @Translation("TFA Email OTP Validation Plugin"),
 *   setupPluginId = "tfa_email_otp_setup",
 * )
 */
class TfaEmailOtpValidation extends TfaBasePlugin implements TfaValidationInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;
  use TfaRandomTrait;

  /**
   * The length of the email TFA OTP.
   */
  public const EMAIL_TFA_OTP_LENGTH = 8;

  /**
   * Maximum number of OTP emails that can be sent per window.
   */
  public const EMAIL_SEND_FLOOD_THRESHOLD = 6;

  /**
   * Time window in seconds for email send flood control.
   */
  public const EMAIL_SEND_FLOOD_WINDOW = 300;

  /**
   * Maximum number of validation attempts via validateRequest per window.
   */
  public const VALIDATE_REQUEST_FLOOD_THRESHOLD = 6;

  /**
   * Time window in seconds for validateRequest flood control.
   */
  public const VALIDATE_REQUEST_FLOOD_WINDOW = 300;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * A mail manager for sending email.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The flood control mechanism.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * Default OTP validity in seconds.
   *
   * @var int
   */
  protected $validityPeriod = 60;

  /**
   * Authentication email setting.
   *
   * @var array
   */
  protected $emailSetting;

  /**
   * Tfa user.
   *
   * @var \Drupal\user\Entity\User
   */
  public $recipient;

  /**
   * Drupal hook_mail array.
   *
   * @var array
   */
  protected $message;

  /**
   * The user's language code.
   *
   * @var string
   */
  protected $langCode;

  /**
   * Encryption profile.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected $encryptionProfile;

  /**
   * Encryption service.
   *
   * @var \Drupal\encrypt\EncryptService
   */
  protected $encryptService;

  /**
   * Constructs a new Tfa plugin object.
   *
   * @param array $configuration
   *   Configuration data for the setup plugin.
   * @param string $plugin_id
   *   The id of the setup plugin.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user\UserDataInterface $user_data
   *   User Data.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   Profile Manager Interface.
   * @param \Drupal\encrypt\EncryptServiceInterface $encrypt_service
   *   Encryption service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager interface.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time interface.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control mechanism.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, MailManagerInterface $mail_manager, TimeInterface $time, FloodInterface $flood) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);

    $this->validityPeriod = $config_factory->get('tfa.settings')->get('validation_plugin_settings.tfa_email_otp.code_validity_period');
    $this->emailSetting = $config_factory->get('tfa.settings')->get('validation_plugin_settings.tfa_email_otp.email_setting');
    $this->loggerFactory = $logger_factory;
    $this->mailManager = $mail_manager;
    $this->time = $time;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('user.data'),
        $container->get('encrypt.encryption_profile.manager'),
        $container->get('encryption'),
        $container->get('config.factory'),
        $container->get('logger.factory'),
        $container->get('plugin.manager.mail'),
        $container->get('datetime.time'),
        $container->get('flood'));
  }

  /**
   * Send the OTP via an email.
   */
  public function send() {
    $userData = $this->userData->get('tfa', $this->uid, 'tfa_email_otp');
    // Generate a true random OTP.
    $code = $this->randomCharacters(static::EMAIL_TFA_OTP_LENGTH, '1234567890');
    // Encrypt the OTP.
    $userData['code'] = $this->encryptService->encrypt($code, $this->encryptionProfile);
    // The expire time for this OTP.
    $userData['expiry'] = $this->time->getCurrentTime() + $this->validityPeriod;
    // Save current OTP to user data table.
    $this->userData->set('tfa', $this->uid, 'tfa_email_otp', $userData);
    // Expire in minutes.
    $length = $this->validityPeriod / 60;

    $this->recipient = User::load($this->uid);
    $search = [
      '[length]',
      '[code]',
    ];
    $replace = [
      $length,
      $code,
    ];
    // Replace tokens in the email body.
    $body = str_replace($search, $replace, $this->emailSetting['body']);

    $this->message = [
      'subject' => $this->emailSetting['subject'],
      'langcode' => $this->langCode,
      'body' => $body,
    ];
    $params = [
      'account' => $this->recipient,
      'message' => $this->message,
    ];
    $logger = $this->loggerFactory->get('tfa');
    $result = $this->mailManager->mail('tfa_email_otp', 'otp_email', $this->recipient->getEmail(), $this->langCode, $params);

    if ($result['result'] != TRUE) {
      $logger->error($this->t('There was a problem sending authentication code to @email.', [
        '@email' => $this->recipient->getEmail(),
      ]));
    }
    else {
      // @phpstan-ignore-next-line
      \Drupal::messenger()->addMessage(t('The authentication code has been sent to your registered email. Check your email and enter the code.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $code_sent = $this->hasActiveOtp();

    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication code'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the code received'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['login'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Verify'),
      '#disabled' => !$code_sent,
    ];
    $form['actions']['send'] = [
      '#type' => 'button',
      '#button_type' => 'primary',
      '#value' => $code_sent ? $this->t('Resend') : $this->t('Send'),
      '#limit_validation_errors' => [['']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function validate($code) {
    $this->isValid = FALSE;

    // Remove empty spaces.
    $code = trim(str_replace(' ', '', $code));
    $userData = $this->userData->get('tfa', $this->uid, 'tfa_email_otp');
    $timestamp = $this->time->getCurrentTime();

    if (empty($userData['expiry'])) {
      return FALSE;
    }

    if ($timestamp > $userData['expiry']) {
      unset($userData['code']);
      unset($userData['expiry']);
      $this->errorMessages['code'] = $this->t('Expired. Please send a new code again.');
      // Remove expired code.
      $this->userData->set('tfa', $this->uid, 'tfa_email_otp', $userData);
      return FALSE;
    }
    // The current OTP.
    $storedCode = $userData['code'];
    try {
      $storedCode = $this->encryptService->decrypt($storedCode, $this->encryptionProfile);
    }
    catch (EncryptException $e) {
      $this->loggerFactory->get('tfa')->error($e->getMessage());
      return FALSE;
    }
    catch (EncryptionMethodCanNotDecryptException $e) {
      $this->loggerFactory->get('tfa')->error($e->getMessage());
      return FALSE;
    }

    if (hash_equals(trim(str_replace(' ', '', $storedCode)), $code)) {
      $this->isValid = TRUE;
      // Remove used code.
      unset($userData['code']);
      unset($userData['expiry']);
      $this->userData->set('tfa', $this->uid, 'tfa_email_otp', $userData);
      return $this->isValid;
    }
    return $this->isValid;
  }

  /**
   * Simple validate for web services.
   *
   * @param string $code
   *   OTP Code.
   *
   * @return bool
   *   True if validation was successful otherwise false.
   */
  public function validateRequest($code) {
    // Check flood control via API.
    $flood_identifier = 'tfa_email_otp_validate_' . $this->uid;
    if (!$this->flood->isAllowed('tfa_email_otp.validate_request', static::VALIDATE_REQUEST_FLOOD_THRESHOLD, static::VALIDATE_REQUEST_FLOOD_WINDOW, $flood_identifier)) {
      return FALSE;
    }

    $result = $this->validate($code);

    if (!$result) {
      // Register failed validation attempt.
      $this->flood->register('tfa_email_otp.validate_request', static::VALIDATE_REQUEST_FLOOD_WINDOW, $flood_identifier);
    }
    else {
      // Clear flood on successful validation.
      $this->flood->clear('tfa_email_otp.validate_request', $flood_identifier);
      $this->flood->clear('tfa_email_otp.send', 'tfa_email_otp_send_' . $this->uid);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // If user is asking for sending the code,
    // no need to validate the input.
    if (isset($values['op']) && in_array($values['op']->getUntranslatedString(), ['Send', 'Resend'], TRUE)) {
      // Check flood control for email sending to prevent email bombing.
      $flood_identifier = 'tfa_email_otp_send_' . $this->uid;
      if (!$this->flood->isAllowed('tfa_email_otp.send', static::EMAIL_SEND_FLOOD_THRESHOLD, static::EMAIL_SEND_FLOOD_WINDOW, $flood_identifier)) {
        $this->errorMessages['send'] = $this->t('Too many code requests. Please wait before requesting another code.');
        return FALSE;
      }

      // Register the send attempt before sending.
      $this->flood->register('tfa_email_otp.send', static::EMAIL_SEND_FLOOD_WINDOW, $flood_identifier);

      // Send user the access code.
      $this->send();

      // TFA entry form requires an array for error messages,
      // when validation failed.
      // As sending a code to user is not an error,
      // We set an empty error message to avoid showing errors.
      $this->errorMessages['send'] = '';
      return FALSE;
    }

    if (!$this->validate($values['code'])) {
      if (!isset($this->errorMessages['code'])) {
        $this->errorMessages['code'] = $this->t('Invalid authentication code. Please try again.');
      }
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * The configuration form for this validation plugin.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Config object for tfa settings.
   * @param array $state
   *   Form state array determines if this form should be shown.
   *
   * @return array
   *   Form array specific for this validation plugin.
   */
  public function buildConfigurationForm(Config $config, array $state = []) {
    $options = [
      60 => 1,
      120 => 2,
      180 => 3,
      240 => 4,
      300 => 5,
      600 => 10,
    ];

    $settings_form = [];

    $settings_form['code_validity_period'] = [
      '#type' => 'select',
      '#title' => $this->t('Code validity period in minutes'),
      '#description' => $this->t('Select the validity period of code sent.'),
      '#options' => $options,
      '#default_value' => $this->validityPeriod,
    ];

    $settings_form['email_setting'] = [
      '#type' => 'details',
      '#title' => $this->t('Authentication code email'),
      '#description' => $this->t('This email is sent the authentication code to the user. <br>Available tokens are: <ul><li>Valid minutes: [length]</li><li>Authentication code: [code]</li><li>Site information: [site]</li><li>User information: [user]</li></ul> Common variables are: [site:name], [site:url], [user:display-name], [user:account-name], and [user:mail].'),
      'subject' => [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $this->emailSetting['subject'] ?? $this->t('[site:name] Authentication code'),
        '#required' => TRUE,
      ],
      'body' => [
        '#type' => 'textarea',
        '#title' => $this->t('Body'),
        '#default_value' => $this->emailSetting['body'] ?? $this->t('[user:display-name],

This code is valid for [length] minutes. Your code is: [code]

This code will expire once you have logged in.'),
        '#required' => TRUE,
        '#attributes' => [
          'rows' => 10,
        ],
      ],
    ];

    return $settings_form;
  }

  /**
   * Determines whether a valid OTP already exists for the user.
   *
   * @return bool
   *   TRUE when a code has been issued and is still valid, FALSE otherwise.
   */
  protected function hasActiveOtp() {
    $userData = $this->userData->get('tfa', $this->uid, 'tfa_email_otp');
    if (!is_array($userData) || empty($userData['code']) || empty($userData['expiry'])) {
      return FALSE;
    }

    if ($this->time->getCurrentTime() > $userData['expiry']) {
      unset($userData['code'], $userData['expiry']);
      $this->userData->set('tfa', $this->uid, 'tfa_email_otp', $userData);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    $user_settings = $this->userData->get('tfa', $this->uid, 'tfa_email_otp');
    $is_enabled = $user_settings['enable'] ?? FALSE;

    return (bool) $is_enabled;
  }

}
