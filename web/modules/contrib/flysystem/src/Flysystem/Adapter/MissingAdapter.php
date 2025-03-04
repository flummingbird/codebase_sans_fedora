<?php

namespace Drupal\flysystem\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

/**
 * An adapter used when a plugin is missing. It fails at everything.
 */
class MissingAdapter implements AdapterInterface {

  /**
   * {@inheritdoc}
   */
  public function copy($path, $newpath): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createDir($dirname, Config $config): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDir($dirname): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function listContents($directory = '', $recursive = FALSE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimetype($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSize($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function has($path): array|bool|null {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibility($path, $visibility): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function update($path, $contents, Config $config): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateStream($path, $resource, Config $config): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function read($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function readStream($path): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path, $newpath): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function write($path, $contents, Config $config): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function writeStream($path, $resource, Config $config): array|false {
    return FALSE;
  }

}
