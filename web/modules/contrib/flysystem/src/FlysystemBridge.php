<?php

namespace Drupal\flysystem;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Util;
use Codementality\FlysystemStreamWrapper\FlysystemStreamWrapper;

/**
 * An adapter for Flysystem to StreamWrapperInterface.
 */
class FlysystemBridge extends FlysystemStreamWrapper implements StreamWrapperInterface {

  use StringTranslationTrait;

  /**
   * PHP-passed stream context.
   *
   * @var resource|null
   */
  public $context;

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    return StreamWrapperInterface::WRITE_VISIBLE;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    $scheme = $this->getProtocol();
    $name = $this->getFactory()->getSettings($scheme)['name'];
    $default = $this->t('Flysystem: @scheme', ['@scheme' => $scheme]);

    return $name !== '' ? $name : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $scheme = $this->getProtocol();
    $description = $this->getFactory()->getSettings($scheme)['description'];
    $default = $this->t('Flysystem: @scheme', ['@scheme' => $scheme]);

    return $description !== '' ? $description : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    return $this
      ->getFactory()
      ->getPlugin($this->getProtocol())
      ->getExternalUrl($this->uri);
  }

  /**
   * {@inheritdoc}
   */
  public function realpath() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    [$scheme, $target] = explode('://', $uri, 2);

    return $scheme . '://' . ltrim(Util::dirname($target), '\/');
  }

  /**
   * Returns the filesystem for a given scheme.
   *
   * @param string $scheme
   *   The scheme.
   *
   * @return \League\Flysystem\FilesystemInterface
   *   The filesystem for the scheme.
   */
  protected function getFilesystemForScheme($scheme) {
    if (!isset(static::$filesystems[$scheme])) {
      static::$filesystems[$scheme] = $this->getFactory()->getFilesystem($scheme);
      static::$config[$scheme] = static::$defaultConfiguration;
      static::$config[$scheme]['permissions']['dir']['public'] = 0777;
      static::registerPlugins($scheme, static::$filesystems[$scheme]);
    }

    return static::$filesystems[$scheme];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilesystem(): FilesystemInterface {
    if (!isset($this->filesystem)) {
      $this->filesystem = $this->getFilesystemForScheme($this->getProtocol());
    }

    return $this->filesystem;
  }

  /**
   * Returns the filesystem factory.
   *
   * @return \Drupal\flysystem\FlysystemFactory
   *   The Flysystem factory.
   */
  protected function getFactory() {
    return \Drupal::service('flysystem_factory');
  }

}
