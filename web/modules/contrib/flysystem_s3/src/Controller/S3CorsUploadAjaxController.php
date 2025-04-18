<?php

namespace Drupal\flysystem_s3\Controller;

use Aws\S3\PostObjectV4;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\Entity\File;
use Drupal\flysystem\FlysystemFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to respond to S3 CORS upload AJAX requests.
 */
class S3CorsUploadAjaxController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\flysystem\FlysystemFactory
   */
  protected $flysystemFactory;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('event_dispatcher'),
      $container->get('flysystem_factory'),
      $container->get('file_system')
    );
  }

  /**
   * Constructs an S3CorsUploadAjaxController object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\flysystem\FlysystemFactory $flysystem_factory
   *   The Flysystem factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, FlysystemFactory $flysystem_factory, FileSystemInterface $file_system) {
    $this->eventDispatcher = $event_dispatcher;
    $this->flysystemFactory = $flysystem_factory;
    $this->fileSystem = $file_system;
  }

  /**
   * Returns the signed request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JsonResponse object.
   */
  public function signRequest(Request $request) {
    $post = $request->request->all();

    $scheme = StreamWrapperManager::getScheme($post['destination']);
    /** @var \Drupal\flysystem_s3\Flysystem\Adapter\S3Adapter $adapter */
    $adapter = $this->flysystemFactory->getPlugin($scheme)->getAdapter();

    $client = $adapter->getClient();
    $bucket = $adapter->getBucket();

    // Prepares the filename.
    $event = new FileUploadSanitizeNameEvent($post['filename'], '');
    $this->eventDispatcher->dispatch($event);

    $destination = $this->fileSystem->getDestinationFilename($post['destination'] . '/' . $event->getFilename(), FileExists::Rename);

    // Apply the prefix to the URI and use it as a key in the POST request.
    $post['key'] = $adapter->applyPathPrefix(StreamWrapperManager::getTarget($destination));

    $options = [
      ['acl' => $post['acl']],
      ['bucket' => $bucket],
      ['starts-with', '$key', $post['key']],
      ['starts-with', '$Content-Type', $post['Content-Type']],
    ];

    // Remove values not necessary for the request to Amazon.
    unset($post['destination']);
    unset($post['filename']);

    // @todo Make this interval configurable.
    $expiration = '+5 hours';
    $postObject = new PostObjectV4($client, $bucket, $post, $options, $expiration);

    $data = [];
    $data['attributes'] = $postObject->getFormAttributes();
    $data['inputs'] = $postObject->getFormInputs();
    $data['options'] = $options;
    $data['url'] = $destination;

    return new JsonResponse($data);
  }

  /**
   * Request handler for /flysystem-s3/cors-upload-save.
   *
   * Create a file object after the file has been successfully uploaded to S3.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JsonResponse with the newly created file id.
   */
  public function saveFile(Request $request) {
    $post = $request->request->all();
    // Create a temporary file to return with a file ID in the response.
    $file = File::create([
      'uri' => $post['url'],
      'filesize' => $post['filesize'],
      'filename' => $this->fileSystem->baseName($post['url']),
      'filemime' => $post['filemime'],
      'uid' => $this->currentUser()->getAccount()->id(),
    ]);
    $file->save();

    return new JsonResponse(['fid' => $file->id()]);
  }

}
