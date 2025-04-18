<?php

namespace Drupal\flysystem\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Asset\AssetDumper as DrupalAssetDumper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;

/**
 * Flysystem dependency injection container.
 *
 * @codeCoverageIgnore
 */
class AssetDumper extends DrupalAssetDumper {

  use SchemeExtensionTrait;

  /**
   * {@inheritdoc}
   */
  public function dump($data, $file_extension) {
    // Prefix filename to prevent blocking by firewalls which reject files
    // starting with "ad*".
    $filename = $file_extension . '_' . Crypt::hashBase64($data) . '.' . $file_extension;
    // Create the css/ or js/ path within the files folder.
    $path = $this->getSchemeForExtension($file_extension) . '://' . $file_extension;
    $uri = $path . '/' . $filename;
    // Create the CSS or JS file.
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);// @phpstan-ignore-line @codingStandardsIgnoreLine 
    if (!file_exists($uri) && !$this->fileSystem->saveData($data, $uri, FileExists::Replace)) {
      return FALSE;
    }
    // If CSS/JS gzip compression is enabled and the zlib extension is available
    // then create a gzipped version of this file. This file is served
    // conditionally to browsers that accept gzip using .htaccess rules.
    // It's possible that the rewrite rules in .htaccess aren't working on this
    // server, but there's no harm (other than the time spent generating the
    // file) in generating the file anyway. Sites on servers where rewrite rules
    // aren't working can set css.gzip to FALSE in order to skip
    // generating a file that won't be used.
    if (extension_loaded('zlib') && \Drupal::config('system.performance')->get($file_extension . '.gzip')) {// @phpstan-ignore-line @codingStandardsIgnoreLine 
      if (!file_exists($uri . '.gz') && !$this->fileSystem->saveData(gzencode($data, 9, FORCE_GZIP), $uri . '.gz', FileExists::Replace)) {
        return FALSE;
      }
    }
    return $uri;
  }

}
