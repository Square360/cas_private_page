<?php

namespace Drupal\cas_private_page\EventSubscriber;

use Drupal\cas\CasRedirectData;
use Drupal\cas\Service\CasRedirector;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for CAS Private Page functionality.
 */
class CasPrivatePageSubscriber implements EventSubscriberInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The CAS redirector service.
   *
   * @var \Drupal\cas\Service\CasRedirector
   */
  protected $casRedirector;

  /**
   * Constructs a new CasPrivatePageSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\cas\Service\CasRedirector $cas_redirector
   *   The CAS redirector service.
   */
  public function __construct(AccountProxyInterface $current_user, CasRedirector $cas_redirector) {
    $this->currentUser = $current_user;
    $this->casRedirector = $cas_redirector;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 30];
    return $events;
  }

  /**
   * Handles the request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    
    // Only process main requests.
    if (!$event->isMainRequest()) {
      return;
    }
    
    // Only process for anonymous users.
    if ($this->currentUser->isAuthenticated()) {
      return;
    }
    
    // Check if this is a node canonical route.
    $route = $request->attributes->get('_route');
    if ($route !== 'entity.node.canonical') {
      return;
    }
    
    // Get the node from the request.
    $node = $request->attributes->get('node');
    if (!($node instanceof NodeInterface)) {
      return;
    }
    
    // Check if the node has the CAS field and it's enabled.
    if ($node->hasField('field_require_cas') && !$node->get('field_require_cas')->isEmpty()) {
      $require_cas = $node->get('field_require_cas')->value;
      
      if ($require_cas) {
        // Build redirect to CAS with current path as destination.
        $current_path = $request->getRequestUri();
        $redirect_data = new CasRedirectData(['destination' => $current_path]);
        $response = $this->casRedirector->buildRedirectResponse($redirect_data);
        
        if ($response) {
          $event->setResponse($response);
        }
      }
    }
  }

}
