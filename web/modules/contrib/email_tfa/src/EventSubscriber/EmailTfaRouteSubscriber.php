<?php

namespace Drupal\email_tfa\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 *
 * Class EmailTfaRouteSubscriber.
 *
 * @package Drupal\tfa\Routing
 */
final class EmailTfaRouteSubscriber extends RouteSubscriberBase {

  /**
   * Overrides user.login route with our custom login form.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   Route to be altered.
   */
  public function alterRoutes(RouteCollection $collection): void {
    // Change path of user login to our overridden TFA login form.
    if ($route = $collection->get('user.login')) {
      $route->setDefault('_form', '\Drupal\email_tfa\Form\EmailTfaLoginForm');
    }

    // Protect REST API login endpoint.
    if ($route = $collection->get('user.login.http')) {
      $route->setDefault('_controller', '\Drupal\email_tfa\Controller\EmailTfaRestLoginController::login');
    }
  }

}
