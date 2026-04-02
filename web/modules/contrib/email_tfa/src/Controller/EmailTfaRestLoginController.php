<?php

namespace Drupal\email_tfa\Controller;

use Drupal\user\Controller\UserAuthenticationController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Provides a protected login controller for REST API with TFA check.
 */
class EmailTfaRestLoginController extends UserAuthenticationController {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Handle different Drupal versions - use user.flood_control if available.
    try {
      $flood_service = $container->get('user.flood_control');
    }
    catch (ServiceNotFoundException $e) {
      $flood_service = $container->get('flood');
    }

    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
      $formats = $container->getParameter('serializer.formats');
    }
    else {
      $formats = ['json'];
      $serializer = new Serializer([], [new JsonEncoder()]);
    }

    return new static(
      $flood_service,
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('csrf_token'),
      $container->get('user.auth'),
      $container->get('router.route_provider'),
      $serializer,
      $formats,
      $container->get('logger.factory')->get('user')
    );
  }

  /**
   * Handles login with TFA protection - blocks REST API login when TFA enabled.
   */
  public function login(Request $request) {
    $config = \Drupal::config('email_tfa.settings');
    $tfa_enabled = $config->get('status');

    // If TFA is not enabled, proceed with normal login.
    if (!$tfa_enabled) {
      return parent::login($request);
    }

    // TFA is enabled - block REST API login completely.
    throw new AccessDeniedHttpException('Two-factor authentication is enabled. REST API login is not supported. Please use the standard login form at /user/login.');
  }

}
