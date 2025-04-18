<?php

namespace Drupal\rest_oai_pmh\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for adding additional content types to OAI-PMH requests.
 */
class OaiDcMimeType implements EventSubscriberInterface {

  /**
   * Register content type formats on the request object.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Event to process.
   */
  public function onKernelRequest($event) {
    $event->getRequest()->setFormat('oai_dc', ['text/xml']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[KernelEvents::REQUEST][] = ['onKernelRequest'];

    return $events;
  }

}
