<?php

namespace Drupal\tfa_email_otp\Plugin\TfaSetup;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tfa\Plugin\TfaSetupInterface;
use Drupal\tfa_email_otp\Plugin\TfaValidation\TfaEmailOtpValidation;

/**
 * TFA Email plugin setup class to setup email otp validation.
 *
 * @TfaSetup(
 *   id = "tfa_email_otp_setup",
 *   label = @Translation("TFA Email OTP Setup"),
 *   description = @Translation("Email OTP Setup Plugin"),
 *   setupMessages = {
 *    "saved" = @Translation("Email OTP set."),
 *    "skipped" = @Translation("Email OTP not enabled.")
 *   }
 * )
 */
class TfaEmailOtpSetup extends TfaEmailOtpValidation implements TfaSetupInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getSetupForm(array $form, FormStateInterface $form_state) {
    $params = $form_state->getValues();
    $userData = $this->userData->get('tfa', $params['account']->id(), 'tfa_email_otp');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Receive authentication one-time code by email'),
      '#description' => $this->t('Enables TFA one-time code be sent by email associated to your account email.'),
      '#required' => TRUE,
      '#default_value' => $userData['enable'] ?? 0,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state) {
    $params = $form_state->getValues();
    $enabled = $form_state->getValue('enabled');
    $uid = $params['account']->id();
    $userData = $this->userData->get('tfa', $uid, 'tfa_email_otp');
    if (!empty($userData) || $enabled) {
      $userData['enable'] = $enabled;
      $this->userData->set('tfa', $uid, 'tfa_email_otp', $userData);
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getHelpLinks() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupMessages() {
    return ($this->pluginDefinition['setupMessages']) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOverview(array $params) {
    $plugin_text = $this->t('Validation Plugin: @plugin', [
      '@plugin' => str_replace(' Setup', '', $this->getLabel()),
    ]);
    $description = $this->t('Send an One Time Password via email.');
    if ($params['enabled']) {
      $description .= '<p><b>' . $this->t('Enabled') . '</b></p>';
    }
    $output = [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Email OTP.'),
      ],
      'validation_plugin' => [
        '#type' => 'markup',
        '#markup' => '<p>' . $plugin_text . '</p>',
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $description,
      ],
      'link' => [
        '#theme' => 'links',
        '#access' => !$params['enabled'],
        '#links' => [
          'admin' => [
            'title' => $this->t('Enable Email OTP'),
            'url' => Url::fromRoute('tfa.validation.setup', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
            ]),
          ],
        ],
      ],
    ];

    return $output;
  }

}
