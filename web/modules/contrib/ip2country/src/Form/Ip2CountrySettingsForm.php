<?php

declare(strict_types=1);

namespace Drupal\ip2country\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserDataInterface;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Drupal\ip2country\Ip2CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ip2country settings for this site.
 */
class Ip2CountrySettingsForm extends ConfigFormBase {

  /**
   * The current user's data.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $stateService;

  /**
   * The date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The country_manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The ip2country.lookup service.
   *
   * @var \Drupal\ip2country\Ip2CountryLookupInterface
   */
  protected $ip2countryLookup;

  /**
   * The ip2country.manager service.
   *
   * @var \Drupal\ip2country\Ip2CountryManagerInterface
   */
  protected $ip2countryManager;

  /**
   * Constructs an Ip2CountryController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\user\UserDataInterface $userData
   *   The current user's data.
   * @param \Drupal\Core\State\StateInterface $stateService
   *   The state service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The country_manager service.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The ip2country.lookup service.
   * @param \Drupal\ip2country\Ip2CountryManagerInterface $ip2countryManager
   *   The ip2country.manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, UserDataInterface $userData, StateInterface $stateService, DateFormatterInterface $dateFormatter, CountryManagerInterface $countryManager, Ip2CountryLookupInterface $ip2countryLookup, Ip2CountryManagerInterface $ip2countryManager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->userData = $userData;
    $this->stateService = $stateService;
    $this->dateFormatter = $dateFormatter;
    $this->countryManager = $countryManager;
    $this->ip2countryLookup = $ip2countryLookup;
    $this->ip2countryManager = $ip2countryManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('user.data'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('country_manager'),
      $container->get('ip2country.lookup'),
      $container->get('ip2country.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip2country_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ip2country.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $ip2country_config = $this->config('ip2country.settings');

    $form['#attached']['library'][] = 'ip2country/ip2country.settings';

    // Container for database update preference forms.
    $form['ip2country_database_update'] = [
      '#type' => 'details',
      '#title' => $this->t('Database updates'),
      '#open' => TRUE,
    ];

    // Form element to enable watchdog logging of updates.
    $form['ip2country_database_update']['ip2country_watchdog'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log database updates to watchdog'),
      '#default_value' => $ip2country_config->get('watchdog'),
    ];

    // Form element to choose RIR.
    $form['ip2country_database_update']['ip2country_rir'] = [
      '#type' => 'select',
      '#title' => $this->t('Regional Internet Registry'),
      // cspell:ignore Tfor
      // phpcs:disable DrupalPractice.General.OptionsT.TforValue
      '#options' => [
        'all' => 'Use all registries',
        'afrinic' => 'AFRINIC',
        'apnic' => 'APNIC',
        'arin' => 'ARIN',
        'lacnic' => 'LACNIC',
        'ripe' => 'RIPE',
      ],
      // cspell:ignore Tfor
      // phpcs:enable DrupalPractice.General.OptionsT.TforValue
      '#default_value' => $ip2country_config->get('rir'),
      '#description' => $this->t('It is recommended to "Use all registries", which allows each regional registry to supply its own data. You may alternatively select one specific RIR. You may find that the regional server nearest you has the best response time, but note that most of these have data that is incomplete or out-of-date to some extent.'),
    ];

    // Form element to enable MD5 checksum of downloaded databases.
    $form['ip2country_database_update']['ip2country_md5_checksum'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Perform MD5 checksum comparison'),
      '#description' => $this->t("Compare MD5 checksum downloaded from the RIR with MD5 checksum calculated locally to ensure the data has not been corrupted. RIRs don't always store current checksums, so if this option is checked your database updates may sometimes fail."),
      '#default_value' => $ip2country_config->get('md5_checksum'),
    ];

    $intervals = [86400, 302400, 604800, 1209600, 2419200];
    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($intervals, $intervals));
    $period[0] = $this->t('Never');

    // Form element to set automatic update interval.
    $form['ip2country_database_update']['ip2country_update_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Database update frequency'),
      '#default_value' => $ip2country_config->get('update_interval'),
      '#options' => $period,
      '#description' => $this->t('Database will be automatically updated via cron.php. Cron must be enabled for this to work. Default period is 1 week (604800 seconds).'),
    ];

    // Form element to customize database insertion batch size.
    $form['ip2country_database_update']['ip2country_update_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Database update batch size'),
      '#default_value' => $ip2country_config->get('batch_size'),
      '#min' => 1,
      '#size' => 8,
      '#required' => TRUE,
      '#description' => $this->t('The number of rows to insert simultaneously. A larger number means faster filling of the database, but more system resources (memory usage).'),
    ];

    $update_time = $this->stateService->get('ip2country_last_update');
    if (!empty($update_time)) {
      $message = $this->t('Database last updated on @date at @time from @registry server.', [
        '@date' => $this->dateFormatter->format($update_time, 'ip2country_date'),
        '@time' => $this->dateFormatter->format($update_time, 'ip2country_time'),
        '@registry' => mb_strtoupper($this->stateService->get('ip2country_last_update_rir')),
      ]);
    }
    else {
      $message = $this->t('Database is empty. You may fill the database by pressing the "Update" button.');
    }

    // Button to initiate manual updating of the IP-Country database.
    $form['ip2country_database_update']['ip2country_update_database'] = [
      '#type' => 'button',
      '#value' => $this->t('Update'),
      '#executes_submit_callback' => FALSE,
      '#prefix' => '<p>' . $this->t('The IP to Country Database may be updated manually by pressing the "Update" button below. Note, this may take several minutes. Changes to the above settings will not be permanently saved unless you press the "Save configuration" button at the bottom of this page.') . '</p>',
      '#suffix' => '<span id="update-message" class="status">' . $message . '</span>',
      '#ajax' => [
        'callback' => [$this, 'updateAction'],
        'disable-refocus' => TRUE,
        'event' => 'click',
        'wrapper' => 'update-message',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Working ...'),
        ],
      ],
    ];

    // Container for manual lookup.
    $form['ip2country_manual_lookup'] = [
      '#type' => 'details',
      '#title' => $this->t('Manual lookup'),
      '#description' => $this->t('Examine database values'),
      '#open' => TRUE,
    ];

    // Form element for IP address for manual lookup.
    $form['ip2country_manual_lookup']['ip2country_lookup'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Manual lookup'),
      '#description' => $this->t('Enter IP address'),
      // '#element_validate' => [[$this, 'validateIp']],
    ];

    // Button to initiate manual lookup.
    $form['ip2country_manual_lookup']['ip2country_lookup_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Lookup'),
      '#executes_submit_callback' => FALSE,
      '#prefix' => '<div>' . $this->t('An IP address may be looked up in the database by entering the address above then pressing the "Lookup" button below.') . '</div>',
      '#suffix' => '<span id="lookup-message" class="message"></span>',
      '#ajax' => [
        'callback' => [$this, 'lookupAction'],
        'disable-refocus' => TRUE,
        'event' => 'click',
        'wrapper' => 'lookup-message',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Working ...'),
        ],
      ],
    ];

    // Container for debugging preferences.
    $form['ip2country_debug_preferences'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug preferences'),
      '#description' => $this->t('Set debugging values'),
      '#open' => TRUE,
    ];

    // Form element to turn on debugging.
    $form['ip2country_debug_preferences']['ip2country_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Admin debug'),
      '#default_value' => $ip2country_config->get('debug'),
      '#description' => $this->t('Enables administrator to spoof an IP Address or Country for debugging purposes.'),
    ];

    // Form element to select Dummy Country or Dummy IP Address for testing.
    $form['ip2country_debug_preferences']['ip2country_test_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select which parameter to spoof'),
      '#default_value' => $ip2country_config->get('test_type'),
      '#options' => [0 => $this->t('Country'), 1 => $this->t('IP Address')],
    ];

    $ip_current = $this->getRequest()->getClientIp();

    // Form element to enter Country to spoof.
    $default_country = $ip2country_config->get('test_country');
    $default_country = empty($default_country) ? $this->ip2countryLookup->getCountry($ip_current) : $default_country;
    $form['ip2country_debug_preferences']['ip2country_test_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country to use for testing'),
      '#default_value' => $default_country,
      '#options' => $this->countryManager->getList(),
      '#states' => [
        'enabled' => [
          ':input[name="ip2country_test_type"]' => ['value' => 0],
        ],
      ],
    ];

    // Form element to enter IP address to spoof.
    $test_ip_address = $ip2country_config->get('test_ip_address');
    $test_ip_address = empty($test_ip_address) ? $ip_current : $test_ip_address;
    $form['ip2country_debug_preferences']['ip2country_test_ip_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP address to use for testing'),
      '#default_value' => $test_ip_address,
      '#element_validate' => [[$this, 'validateIp']],
      '#states' => [
        'enabled' => [
          ':input[name="ip2country_test_type"]' => ['value' => 1],
        ],
      ],
    ];

    // Parent adds "Save configuration" button, among other things.
    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback to handle requests to update the database.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A form array to replace the designated wrapper.
   */
  public function updateAction(array &$form, FormStateInterface $form_state): array {
    $ip2country_config = $this->config('ip2country.settings');
    $watchdog = $ip2country_config->get('watchdog');

    $rir = $form_state->getValue('ip2country_rir');
    $md5_checksum = (bool) $form_state->getValue('ip2country_md5_checksum');
    $batch_size = (int) $form_state->getValue('ip2country_update_batch_size');

    // Update DB from RIR.
    $status = $this->ip2countryManager->updateDatabase($rir, $md5_checksum, $batch_size);

    if ($status != FALSE) {
      if ($watchdog) {
        $this->logger('ip2country')->notice('Manual database update from @registry server. Table contains @rows rows.', [
          '@registry' => mb_strtoupper($rir),
          '@rows' => $this->ip2countryManager->getRowCount(),
        ]);
      }
      return [
        '#prefix' => '<span id="update-message" class="status">',
        '#markup' => $this->t('The IP to Country database has been updated from @server. @rows rows affected.', [
          '@server' => mb_strtoupper($rir),
          '@rows' => $this->ip2countryManager->getRowCount(),
        ]),
        '#suffix' => '</span>',
      ];
    }
    else {
      if ($watchdog) {
        $this->logger('ip2country')->notice('Manual database update from @registry server FAILED.', [
          '@registry' => mb_strtoupper($rir),
        ]);
      }
      return [
        '#prefix' => '<span id="update-message" class="error">',
        '#markup' => $this->t('The IP to Country database update failed. @rows rows affected.', [
          '@rows' => 0,
        ]),
        '#suffix' => '</span>',
      ];
    }
  }

  /**
   * Ajax callback to handle requests to update the database.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A form array to replace the designated wrapper.
   */
  public function lookupAction(array &$form, FormStateInterface $form_state): array {
    // Be tolerant of any unintentional leading and trailing whitespace.
    $ip_address = trim($form_state->getValue('ip2country_lookup'));
    // Ensure the entered IP address has the correct format.
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
      return [
        '#prefix' => '<span id="lookup-message" class="error">',
        '#markup' => $this->t('The IP address you entered is invalid. Please enter an address in the form xxx.xxx.xxx.xxx where xxx is between 0 and 255 inclusive.'),
        '#suffix' => '</span>',
      ];
    }

    // Return results of manual lookup.
    $country_code = $this->ip2countryLookup->getCountry($ip_address);
    if ($country_code) {
      $country_list = $this->countryManager->getList();
      // @todo Implement hook_countries_alter() (?) to add EU to the list of
      // countries returned by the core Drupal country.manager service.
      // Are there other pseudo-countries like this in the registry data that
      // Drupal doesn't know about by default?
      if ($country_code === 'EU') {
        $country_name = $this->t('European Union');
      }
      else {
        $country_name = $country_list[$country_code];
      }
      return [
        '#prefix' => '<span id="lookup-message" class="status">',
        '#markup' => $this->t('IP Address @ip is assigned to @country (@code).', [
          '@ip' => $ip_address,
          '@country' => $country_name,
          '@code' => $country_code,
        ]),
        '#suffix' => '</span>',
      ];
    }
    else {
      // Didn't find anything.
      return [
        '#prefix' => '<span id="lookup-message" class="status">',
        '#markup' => $this->t('IP Address @ip is not assigned to a country.', [
          '@ip' => $ip_address,
        ]),
        '#suffix' => '</span>',
      ];
    }
  }

  /**
   * Element validation handler for IP address input.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateIp(array $element, FormStateInterface $form_state, array &$complete_form): void {
    $ip_address = $element['#value'];
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
      $form_state->setError($element, $this->t('The IP address you entered is invalid. Please enter an address in the form xxx.xxx.xxx.xxx where xxx is between 0 and 255 inclusive.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // phpcs:enable
    // There is nothing we need to explicitly validate here. All user entries
    // are either constrained, or validation is done by the relevant form
    // elements. This method is present only to document this intent.
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $ip2country_config = $this->config('ip2country.settings');
    $ip2country_config
      ->setData([
        'watchdog' => (bool) $values['ip2country_watchdog'],
        'rir' => (string) $values['ip2country_rir'],
        'md5_checksum' => (bool) $values['ip2country_md5_checksum'],
        'update_interval' => (integer) $values['ip2country_update_interval'],
        'batch_size' => (integer) $values['ip2country_update_batch_size'],
        'debug' => (bool) $values['ip2country_debug'],
        'test_type' => (integer) $values['ip2country_test_type'],
        'test_country' => (string) $values['ip2country_test_country'],
        'test_ip_address' => (string) trim($values['ip2country_test_ip_address']),
      ])
      ->save();

    // Check to see if debug set.
    if ($values['ip2country_debug']) {
      // Debug on.
      if ($values['ip2country_test_type']) {
        // Dummy IP Address.
        $ip = trim($values['ip2country_test_ip_address']);
        $country_code = $this->ip2countryLookup->getCountry($ip);
      }
      else {
        // Dummy Country.
        $country_code = $values['ip2country_test_country'];
      }
      $country_list = $this->countryManager->getList();
      $country_name = $country_list[$country_code];
      $this->messenger()->addMessage(
        $this->t('Using DEBUG value for Country - @country (@code)', [
          '@country' => $country_name,
          '@code' => $country_code,
        ])
      );
    }
    else {
      // Debug off - make sure we set/reset IP/Country to their real values.
      $ip = $this->getRequest()->getClientIp();
      $country_code = $this->ip2countryLookup->getCountry($ip);
      $this->messenger()->addMessage(
        $this->t('Using ACTUAL value for Country - @country', [
          '@country' => $country_code,
        ])
      );
    }

    // Finally, save country, if it has been determined.
    if ($country_code) {
      // Store the ISO country code in the $user object.
      $account = $this->currentUser();
      $account->country_iso_code_2 = $country_code;
      $this->userData->set('ip2country', $account->id(), 'country_iso_code_2', $country_code);
    }

    parent::submitForm($form, $form_state);
  }

}
