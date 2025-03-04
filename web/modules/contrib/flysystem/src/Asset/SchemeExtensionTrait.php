<?php

namespace Drupal\flysystem\Asset;

use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\AssetsStream;

/**
 * Flysystem dependency injection container.
 */
trait SchemeExtensionTrait {

  /**
   * Returns the scheme that should serve an extension.
   *
   * @param string $extension
   *   The extension.
   *
   * @return string
   *   The scheme that should serve the extension.
   */
  public function getSchemeForExtension($extension) {
    $has_assets_scheme = class_exists(AssetsStream::class);
    $extension_scheme = 'assets';

    foreach (Settings::get('flysystem', []) as $scheme => $configuration) {
      if (!empty($configuration['serve_' . $extension]) && !empty($configuration['driver'])) {
        if ($has_assets_scheme) {
          @trigger_error(sprintf('The serve_%s Flysystem option is deprecated in flysystem:2.1.0 and is removed from flysystem:2.3.0. Use the assets:// stream wrapper instead. See https://www.drupal.org/node/3328126', $extension), E_USER_DEPRECATED);
        }
        // Don't break, the last configured one will win.
        $extension_scheme = $scheme;
      }
    }

    return $extension_scheme;
  }

}
