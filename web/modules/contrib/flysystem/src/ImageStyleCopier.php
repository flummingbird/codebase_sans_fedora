<?php

namespace Drupal\flysystem;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\image\ImageStyleInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Copies an image style from temporary storage to a flysystem adapter.
 *
 * This class is registered to run on the kernel's terminate event so it doesn't
 * block image delivery.
 */
class ImageStyleCopier implements EventSubscriberInterface {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * An array of image derivatives to copy.
   *
   * @var array
   */
  protected $copyTasks = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The lock backend interface.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs an ImageStyleCopier.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Psr\Log\LoggerInterface $logger
   *   The system logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(
    LockBackendInterface $lock,
    FileSystemInterface $file_system,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
  ) {
    $this->lock = $lock;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::TERMINATE] = 'processCopyTasks';

    return $events;
  }

  /**
   * Adds a task to generate and copy an image derivative.
   *
   * @param string $temporary_uri
   *   The URI of the temporary image to copy from.
   * @param string $source_uri
   *   The URI of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style being copied.
   */
  public function addCopyTask($temporary_uri, $source_uri, ImageStyleInterface $image_style) {
    $this->copyTasks[] = func_get_args();
  }

  /**
   * Processes all image copy tasks.
   */
  public function processCopyTasks() {
    foreach ($this->copyTasks as $task) {
      [$temporary_uri, $source_uri, $image_style] = $task;
      $this->copyToAdapter($temporary_uri, $source_uri, $image_style);
    }

    $this->copyTasks = [];
  }

  /**
   * Generates an image with the remote stream wrapper.
   *
   * @param string $temporary_uri
   *   The temporary file URI to copy to the adapter.
   * @param string $source_uri
   *   The URI of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   */
  protected function copyToAdapter($temporary_uri, $source_uri, ImageStyleInterface $image_style) {
    $derivative_uri = $image_style->buildUri($source_uri);

    // file_unmanaged_copy() doesn't distinguish between a FALSE return due to
    // and error or a FALSE return due to an existing file. If we can't acquire
    // this lock, we know another thread is uploading the image and we ignore
    // uploading it in this thread.
    $lock_name = 'flysystem_copy_to_adapter:' . $image_style->id() . ':' . Crypt::hashBase64($source_uri);

    if (!$this->lock->acquire($lock_name)) {
      $this->logger->info('Another copy of %image to %destination is in progress',
      [
        '%image' => $temporary_uri,
        '%destination' => $derivative_uri,
      ]);
      return;
    }

    try {
      // Get the folder for the final location of this style.
      $directory = $this->fileSystem->dirname($derivative_uri);

      // Build the destination folder tree if it doesn't already exist.
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $this->logger->error('Failed to create image style directory: %directory', ['%directory' => $directory]);
        return;
      }

      if (!$this->fileSystem->copy($temporary_uri, $derivative_uri, FileExists::Replace)) {
        $this->logger->error('Unable to copy %image to %destination', [
          '%image' => $temporary_uri,
          '%directory' => $directory,
        ]);
        return;
      }

    }
    finally {
      $this->fileSystem->delete($temporary_uri);
      $this->invalidateTags($source_uri);
      $this->lock->release($lock_name);
    }
  }

  /**
   * Invalidates the cache tags for a file URI.
   *
   * @param string $uri
   *   The file URI.
   */
  protected function invalidateTags($uri) {
    $file = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['uri' => $uri]);

    if ($file) {
      $file = reset($file);
      $this->cacheTagsInvalidator->invalidateTags($file->getCacheTagsToInvalidate());
    }
  }

}
