<?php

declare(strict_types=1);

namespace Drupal\ip2country\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;

/**
 * IP address constraint.
 */
#[Constraint(
  id: 'IpAddress',
  label: new TranslatableMarkup('IP address', [], ['context' => 'Validation']),
  type: 'ip_address'
)]
class IpAddressConstraint extends Ip {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IpValidator::class;
  }

}
