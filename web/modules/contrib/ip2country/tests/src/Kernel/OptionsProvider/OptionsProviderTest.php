<?php

declare(strict_types=1);

namespace Drupal\Tests\ip2country\Kernel\OptionsProvider;

use Drupal\Core\Form\OptGroup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ip2country\TypedData\Options\CountryListOptions;

/**
 * Tests use of option providers.
 *
 * @group ip2country
 */
class OptionsProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['rules', 'typed_data'];

  /**
   * The class resolver service used to instantiate options providers.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolver
   */
  protected $classResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The core OptionsProviderResolver uses this service to instantiate
    // options providers when given a ::class.
    $this->classResolver = $this->container->get('class_resolver');
  }

  /**
   * Tests output of options providers.
   *
   * @param string $definition
   *   A string class constant to identify the options provider class to test.
   * @param array $options
   *   An associative array containing the 'value' => 'option' pairs expected
   *   from the options provider being tested.
   *
   * @dataProvider provideOptionsProviders
   */
  public function testOptionsProvider(string $definition, array $options): void {
    $provider = $this->classResolver->getInstanceFromDefinition($definition);

    $flatten_options = OptGroup::flattenOptions($options);
    $values = array_keys($flatten_options);

    $this->assertNotNull($provider);
    $this->assertEquals($options, array_map(fn($value): string => (string) $value, $provider->getPossibleOptions()));
    $this->assertEquals($values, array_map(fn($value): string => (string) $value, $provider->getPossibleValues()));
    $this->assertEquals($options, array_map(fn($value): string => (string) $value, $provider->getSettableOptions()));
    $this->assertEquals($values, array_map(fn($value): string => (string) $value, $provider->getSettableValues()));
  }

  /**
   * Provides test data for testOptionsProviders().
   */
  public static function provideOptionsProviders(): array {
    return [
      'Countries' => [
        CountryListOptions::class, [
          // cspell:disable
          'AF' => 'Afghanistan',
          'AL' => 'Albania',
          'DZ' => 'Algeria',
          'AS' => 'American Samoa',
          'AD' => 'Andorra',
          'AO' => 'Angola',
          'AI' => 'Anguilla',
          'AQ' => 'Antarctica',
          'AG' => 'Antigua & Barbuda',
          'AR' => 'Argentina',
          'AM' => 'Armenia',
          'AW' => 'Aruba',
          'AC' => 'Ascension Island',
          'AU' => 'Australia',
          'AT' => 'Austria',
          'AZ' => 'Azerbaijan',
          'BS' => 'Bahamas',
          'BH' => 'Bahrain',
          'BD' => 'Bangladesh',
          'BB' => 'Barbados',
          'BY' => 'Belarus',
          'BE' => 'Belgium',
          'BZ' => 'Belize',
          'BJ' => 'Benin',
          'BM' => 'Bermuda',
          'BT' => 'Bhutan',
          'BO' => 'Bolivia',
          'BA' => 'Bosnia & Herzegovina',
          'BW' => 'Botswana',
          'BV' => 'Bouvet Island',
          'BR' => 'Brazil',
          'IO' => 'British Indian Ocean Territory',
          'VG' => 'British Virgin Islands',
          'BN' => 'Brunei',
          'BG' => 'Bulgaria',
          'BF' => 'Burkina Faso',
          'BI' => 'Burundi',
          'KH' => 'Cambodia',
          'CM' => 'Cameroon',
          'CA' => 'Canada',
          'IC' => 'Canary Islands',
          'CV' => 'Cape Verde',
          'BQ' => 'Caribbean Netherlands',
          'KY' => 'Cayman Islands',
          'CF' => 'Central African Republic',
          'EA' => 'Ceuta & Melilla',
          'TD' => 'Chad',
          'CL' => 'Chile',
          'CN' => 'China',
          'CX' => 'Christmas Island',
          'CP' => 'Clipperton Island',
          'CC' => 'Cocos (Keeling) Islands',
          'CO' => 'Colombia',
          'KM' => 'Comoros',
          'CG' => 'Congo - Brazzaville',
          'CD' => 'Congo - Kinshasa',
          'CK' => 'Cook Islands',
          'CR' => 'Costa Rica',
          'HR' => 'Croatia',
          'CU' => 'Cuba',
          'CW' => 'Curaçao',
          'CY' => 'Cyprus',
          'CZ' => 'Czechia',
          'CI' => 'Côte d’Ivoire',
          'DK' => 'Denmark',
          'DG' => 'Diego Garcia',
          'DJ' => 'Djibouti',
          'DM' => 'Dominica',
          'DO' => 'Dominican Republic',
          'EC' => 'Ecuador',
          'EG' => 'Egypt',
          'SV' => 'El Salvador',
          'GQ' => 'Equatorial Guinea',
          'ER' => 'Eritrea',
          'EE' => 'Estonia',
          'SZ' => 'Eswatini',
          'ET' => 'Ethiopia',
          'FK' => 'Falkland Islands',
          'FO' => 'Faroe Islands',
          'FJ' => 'Fiji',
          'FI' => 'Finland',
          'FR' => 'France',
          'GF' => 'French Guiana',
          'PF' => 'French Polynesia',
          'TF' => 'French Southern Territories',
          'GA' => 'Gabon',
          'GM' => 'Gambia',
          'GE' => 'Georgia',
          'DE' => 'Germany',
          'GH' => 'Ghana',
          'GI' => 'Gibraltar',
          'GR' => 'Greece',
          'GL' => 'Greenland',
          'GD' => 'Grenada',
          'GP' => 'Guadeloupe',
          'GU' => 'Guam',
          'GT' => 'Guatemala',
          'GG' => 'Guernsey',
          'GN' => 'Guinea',
          'GW' => 'Guinea-Bissau',
          'GY' => 'Guyana',
          'HT' => 'Haiti',
          'HM' => 'Heard & McDonald Islands',
          'HN' => 'Honduras',
          'HK' => 'Hong Kong SAR China',
          'HU' => 'Hungary',
          'IS' => 'Iceland',
          'IN' => 'India',
          'ID' => 'Indonesia',
          'IR' => 'Iran',
          'IQ' => 'Iraq',
          'IE' => 'Ireland',
          'IM' => 'Isle of Man',
          'IL' => 'Israel',
          'IT' => 'Italy',
          'JM' => 'Jamaica',
          'JP' => 'Japan',
          'JE' => 'Jersey',
          'JO' => 'Jordan',
          'KZ' => 'Kazakhstan',
          'KE' => 'Kenya',
          'KI' => 'Kiribati',
          'XK' => 'Kosovo',
          'KW' => 'Kuwait',
          'KG' => 'Kyrgyzstan',
          'LA' => 'Laos',
          'LV' => 'Latvia',
          'LB' => 'Lebanon',
          'LS' => 'Lesotho',
          'LR' => 'Liberia',
          'LY' => 'Libya',
          'LI' => 'Liechtenstein',
          'LT' => 'Lithuania',
          'LU' => 'Luxembourg',
          'MO' => 'Macao SAR China',
          'MG' => 'Madagascar',
          'MW' => 'Malawi',
          'MY' => 'Malaysia',
          'MV' => 'Maldives',
          'ML' => 'Mali',
          'MT' => 'Malta',
          'MH' => 'Marshall Islands',
          'MQ' => 'Martinique',
          'MR' => 'Mauritania',
          'MU' => 'Mauritius',
          'YT' => 'Mayotte',
          'MX' => 'Mexico',
          'FM' => 'Micronesia',
          'MD' => 'Moldova',
          'MC' => 'Monaco',
          'MN' => 'Mongolia',
          'ME' => 'Montenegro',
          'MS' => 'Montserrat',
          'MA' => 'Morocco',
          'MZ' => 'Mozambique',
          'MM' => 'Myanmar (Burma)',
          'NA' => 'Namibia',
          'NR' => 'Nauru',
          'NP' => 'Nepal',
          'NL' => 'Netherlands',
          'AN' => 'Netherlands Antilles',
          'NC' => 'New Caledonia',
          'NZ' => 'New Zealand',
          'NI' => 'Nicaragua',
          'NE' => 'Niger',
          'NG' => 'Nigeria',
          'NU' => 'Niue',
          'NF' => 'Norfolk Island',
          'MP' => 'Northern Mariana Islands',
          'KP' => 'North Korea',
          'MK' => 'North Macedonia',
          'NO' => 'Norway',
          'OM' => 'Oman',
          'QO' => 'Outlying Oceania',
          'PK' => 'Pakistan',
          'PW' => 'Palau',
          'PS' => 'Palestinian Territories',
          'PA' => 'Panama',
          'PG' => 'Papua New Guinea',
          'PY' => 'Paraguay',
          'PE' => 'Peru',
          'PH' => 'Philippines',
          'PN' => 'Pitcairn Islands',
          'PL' => 'Poland',
          'PT' => 'Portugal',
          'PR' => 'Puerto Rico',
          'QA' => 'Qatar',
          'RO' => 'Romania',
          'RU' => 'Russia',
          'RW' => 'Rwanda',
          'RE' => 'Réunion',
          'WS' => 'Samoa',
          'SM' => 'San Marino',
          'CQ' => 'Sark',
          'SA' => 'Saudi Arabia',
          'SN' => 'Senegal',
          'RS' => 'Serbia',
          'SC' => 'Seychelles',
          'SL' => 'Sierra Leone',
          'SG' => 'Singapore',
          'SX' => 'Sint Maarten',
          'SK' => 'Slovakia',
          'SI' => 'Slovenia',
          'SB' => 'Solomon Islands',
          'SO' => 'Somalia',
          'ZA' => 'South Africa',
          'GS' => 'South Georgia & South Sandwich Islands',
          'KR' => 'South Korea',
          'SS' => 'South Sudan',
          'ES' => 'Spain',
          'LK' => 'Sri Lanka',
          'BL' => 'St. Barthélemy',
          'SH' => 'St. Helena',
          'KN' => 'St. Kitts & Nevis',
          'LC' => 'St. Lucia',
          'MF' => 'St. Martin',
          'PM' => 'St. Pierre & Miquelon',
          'VC' => 'St. Vincent & Grenadines',
          'SD' => 'Sudan',
          'SR' => 'Suriname',
          'SJ' => 'Svalbard & Jan Mayen',
          'SE' => 'Sweden',
          'CH' => 'Switzerland',
          'SY' => 'Syria',
          'ST' => 'São Tomé & Príncipe',
          'TW' => 'Taiwan',
          'TJ' => 'Tajikistan',
          'TZ' => 'Tanzania',
          'TH' => 'Thailand',
          'TL' => 'Timor-Leste',
          'TG' => 'Togo',
          'TK' => 'Tokelau',
          'TO' => 'Tonga',
          'TT' => 'Trinidad & Tobago',
          'TA' => 'Tristan da Cunha',
          'TN' => 'Tunisia',
          'TM' => 'Turkmenistan',
          'TC' => 'Turks & Caicos Islands',
          'TV' => 'Tuvalu',
          'TR' => 'Türkiye',
          'UM' => 'U.S. Outlying Islands',
          'VI' => 'U.S. Virgin Islands',
          'UG' => 'Uganda',
          'UA' => 'Ukraine',
          'AE' => 'United Arab Emirates',
          'GB' => 'United Kingdom',
          'US' => 'United States',
          'UY' => 'Uruguay',
          'UZ' => 'Uzbekistan',
          'VU' => 'Vanuatu',
          'VA' => 'Vatican City',
          'VE' => 'Venezuela',
          'VN' => 'Vietnam',
          'WF' => 'Wallis & Futuna',
          'EH' => 'Western Sahara',
          'YE' => 'Yemen',
          'ZM' => 'Zambia',
          'ZW' => 'Zimbabwe',
          'AX' => 'Åland Islands',
          // cspell:enable
        ],
      ],
    ];
  }

}
