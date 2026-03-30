<?php

namespace Drupal\ip_language_negotiation\Form;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * IP language negotiation form.
 */
class IpLanguageNegotiationForm extends FormBase {

  use StringTranslationTrait;

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new IpLanguageNegotiationForm.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The country manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(CountryManagerInterface $countryManager, LanguageManagerInterface $languageManager) {
    $this->countryManager = $countryManager;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_language_negotiation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $countries = $this->countryManager->getList();
    $languages = $this->languageManager->getLanguages();
    $language_default = $this->languageManager->getDefaultLanguage();
    $settings = $this->config('ip_language_negotiation.settings')->get('ip_language_negotiation_countries') ?: [];

    $ip2country_settings_link = Url::fromRoute('ip2country.settings', ['fragment' => 'edit-ip2country-debug-preferences'])
      ->toString();
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Use the interface below to select the default language per country. You only have to set the exceptions, because the default language will be used as fall-back. You can use the <a href="@url">Debug preferences</a> to test the module.', ['@url' => $ip2country_settings_link]) . '</p>',
    ];

    // Remove the default language.
    unset($languages[$language_default->getId()]);

    // Build languages options array.
    $language_options = [
      '' => $this->t('Default (@default_language)', [
        '@default_language' => $language_default->getName(),
      ]),
    ];
    foreach ($languages as $language) {
      $language_options[$language->getId()] = $language->getName();
    }

    $letter = '';
    foreach ($countries as $country_code => $country) {
      // Remove accents so we can sort countries correctly.
      $current_letter = iconv('UTF-8', 'ASCII//TRANSLIT', mb_substr($country, 0, 1));

      if ($letter != $current_letter) {
        $letter = $current_letter;
        if (empty($form['ip_language_letter_' . $letter])) {
          $form['ip_language_letter_' . $letter] = [
            '#type' => 'fieldset',
            '#collapsible' => TRUE,
            '#collapsed' => TRUE,
            '#title' => $this->t('Countries with the letter %letter', [
              '%letter' => $letter,
            ]),
          ];
        }
      }
      $form['ip_language_letter_' . $letter][$country_code] = [
        '#type' => 'radios',
        '#options' => $language_options,
        '#title' => $country,
        '#default_value' => '',
      ];
      if (!empty($settings[$country_code])) {
        $form['ip_language_letter_' . $letter][$country_code]['#default_value'] = $settings[$country_code];
        $form['ip_language_letter_' . $letter]['#collapsed'] = FALSE;
      }
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Exclude unnecessary elements.
    $form_state->cleanValues();
    $this->configFactory()
      ->getEditable('ip_language_negotiation.settings')
      ->set('ip_language_negotiation_countries', $form_state->getValues())
      ->save();
  }

}
