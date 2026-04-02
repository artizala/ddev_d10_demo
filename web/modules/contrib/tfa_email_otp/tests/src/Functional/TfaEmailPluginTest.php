<?php

namespace Drupal\Tests\tfa_email_otp\Functional;

use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\tfa\Functional\TfaTestBase;
use Drupal\Tests\WebAssert;
use Drupal\tfa_email_otp\Plugin\TfaSetup\TfaEmailOtpSetup;

/**
 * Tests for the Email OTP login process.
 *
 * @group tfa
 */
class TfaEmailPluginTest extends TfaTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'tfa_test_plugins',
    'tfa',
    'encrypt',
    'encrypt_test',
    'key',
    'tfa_email_otp',
  ];
  /**
   * User doing the TFA Validation.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * Administrator to handle configurations.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->webUser = $this->drupalCreateUser([
      'setup own tfa',
    ]);
    $this->adminUser = $this->drupalCreateUser([
      'admin tfa settings',
    ]);
    $this->canEnableValidationPlugin('tfa_email_otp');
  }

  /**
   * Tests behavior of Email TFA login process.
   */
  public function testEmailOtp() {
    $assert_session = $this->assertSession();

    // Enable Email TFA for the webUser role.
    $this->setupEmailOtp($assert_session);

    // Log in as the web user.
    $this->drupalLogout();

    // Login the web user.
    $this->loginWebUser();
    // Enable the email TFA for web user.
    $this->enableEmailOtp();
    $assert_session->pageTextContains('Enabled');
    $this->drupalLogout();
    $this->loginWebUser();
    $assert_session->addressMatches('/\/tfa\/' . $this->webUser->id() . '/');
    $verify_button = $assert_session->buttonExists('Verify');
    $this->assertTrue($verify_button->hasAttribute('disabled'), 'Verify button should be disabled before a code is sent.');
    // Click the send button to send the code via an email.
    $this->submitForm([], 'Send');
    $verify_button = $assert_session->buttonExists('Verify');
    $this->assertFalse($verify_button->hasAttribute('disabled'), 'Verify button should be enabled after a code is sent.');
    $assert_session->buttonExists('Resend');
    $assert_session->pageTextContains('The authentication code has been sent to your registered email. Check your email and enter the code.');
    // At this point, user should not be authenticated yet.
    $this->isUnauthorized($assert_session);

    $this->loginWebUser();
    // Test an empty code.
    $this->submitForm([], 'Verify');
    // At this point, user should still not be authenticated yet.
    $this->isUnauthorized($assert_session);

    // Try a random code, which should fail.
    $this->loginWebUser();
    $edit = [
      'edit-code' => $this->randomString(TfaEmailOtpSetup::EMAIL_TFA_OTP_LENGTH),
    ];
    $this->submitForm($edit, 'Verify');
    $assert_session->pageTextContains('Invalid authentication code.');
    // Check whether the login failed.
    $this->isUnauthorized($assert_session);

    // Now try the valid code.
    // The TFA code sent by an email.
    // Login the web user with email TFA.
    $this->loginWebUser();
    $code = $this->getEmailTfaCode();
    $edit = [
      'edit-code' => $code,
    ];
    $this->submitForm($edit, 'Verify');
    // The web user should login successfully now.
    $this->drupalGet('user/' . $this->webUser->id() . '/edit');
    $assert_session->statusCodeEquals(200);

    // Test an used code.
    $this->drupalLogout();
    $this->loginWebUser();
    $edit = [
      'edit-code' => $code,
    ];
    $this->submitOtpSend();
    $this->submitForm($edit, 'Verify');
    $assert_session->pageTextContains('Invalid authentication code.');
    // Check whether the login failed.
    $this->isUnauthorized($assert_session);

    // Test the validity period.
    $settings = $this->config('tfa.settings');
    $seconds = (int) $settings->get('validation_plugin_settings.tfa_email_otp.code_validity_period');
    $this->loginWebUser();
    // Click the send button to send the code via an email.
    $this->submitOtpSend();
    $code = $this->getEmailTfaCode();
    $edit = [
      'edit-code' => $code,
    ];
    // Wait for the validity period over.
    sleep($seconds + 1);
    $this->submitForm($edit, 'Verify');
    $assert_session->pageTextContains('Expired. Please send a new code again.');
    // Check whether the login failed.
    $this->isUnauthorized($assert_session);

    // Test flood control.
    $this->loginWebUser();
    $flood = 6;
    for ($i = 0; $i < $flood; $i++) {
      $this->submitOtpSend();
    }
    $assert_session->pageTextContains('Failed validation limit reached.');
    // User shouldn't be signed in.
    $this->isUnauthorized($assert_session);
  }

  /**
   * Retrieves TFA code sent by an email.
   */
  protected function getEmailTfaCode() {
    // Assume the most recent email.
    $_emails = $this->drupalGetMails();
    $email = end($_emails);
    $body = $email['body'];
    $start = strpos($body, 'Your code is: ') + strlen('Your code is: ');
    $code = substr($body, $start, TfaEmailOtpSetup::EMAIL_TFA_OTP_LENGTH);
    $this->assertNotEmpty($code);

    return $code;
  }

  /**
   * Assert the user hasn't been signed in yet.
   *
   * @param \Drupal\Tests\WebAssert $assert_session
   *   Current assert session.
   */
  protected function isUnauthorized(WebAssert $assert_session) {
    $this->drupalGet('user/' . $this->webUser->id() . '/edit');
    $assert_session->statusCodeEquals(403);
  }

  /**
   * Set up Email OTP for Web user role.
   *
   * @param \Drupal\Tests\WebAssert $assert_session
   *   Current assert session.
   */
  protected function setupEmailOtp(WebAssert $assert_session) {
    $this->drupalLogin($this->adminUser);
    $web_user_roles = $this->webUser->getRoles(TRUE);
    $edit = [
      'tfa_required_roles[' . $web_user_roles[0] . ']' => TRUE,
    ];
    $this->drupalGet('admin/config/people/tfa');
    $this->submitForm($edit, 'Save configuration');
    $assert_session->statusCodeEquals(200);
    $assert_session->checkboxChecked('tfa_allowed_validation_plugins[tfa_email_otp]');
    $assert_session->checkboxChecked('tfa_required_roles[' . $web_user_roles[0] . ']');
  }

  /**
   * Login web user by user name and password.
   */
  protected function loginWebUser() {
    $edit = [
      'name' => $this->webUser->getAccountName(),
      'pass' => $this->webUser->passRaw,
    ];
    $this->drupalGet('user/login');
    $this->submitForm($edit, 'Log in');
  }

  /**
   * Enable Email OTP for web user.
   */
  protected function enableEmailOtp() {
    $this->drupalGet('user/' . $this->webUser->id() . '/security/tfa/tfa_email_otp');
    $edit = [
      'current_pass' => $this->webUser->passRaw,
    ];
    $this->submitForm($edit, 'Confirm');
    $edit = [
      'enabled' => 1,
    ];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Clicks the send/resend button depending on current state.
   */
  protected function submitOtpSend(): void {
    $assert_session = $this->assertSession();
    try {
      $assert_session->buttonExists('Send');
      $this->submitForm([], 'Send');
      return;
    }
    catch (ElementNotFoundException $exception) {
      // Fall through to attempt Resend below.
    }

    $assert_session->buttonExists('Resend');
    $this->submitForm([], 'Resend');
  }

}
