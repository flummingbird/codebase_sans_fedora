<?php

namespace Drupal\flysystem\Asset;

use Drupal\Core\Asset\JsCollectionOptimizer as DrupalJsCollectionOptimizer;

/**
 * Optimizes JavaScript assets.
 */
class JsCollectionOptimizer extends DrupalJsCollectionOptimizer {// @phpstan-ignore-line @codingStandardsIgnoreLine 

  use SchemeExtensionTrait;

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->state->delete('system.js_cache_files');
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = $this->fileSystem;
    $delete_stale = static function ($uri) use ($file_system) {
      // Default stale file threshold is 30 days (2592000 seconds).
      $stale_file_threshold = \Drupal::config('system.performance')->get('stale_file_threshold') ?? 2592000;// @phpstan-ignore-line @codingStandardsIgnoreLine 
      if (\Drupal::time()->getRequestTime() - filemtime($uri) > $stale_file_threshold) {// @phpstan-ignore-line @codingStandardsIgnoreLine 
        try {
          $file_system->delete($uri);
        }
        catch (\Exception $e) {
          \Drupal::service('logger.factory')->get('flysystem')->error($e->getMessage());// @phpstan-ignore-line @codingStandardsIgnoreLine 
        }
      }
    };
    $js_dir = $this->getSchemeForExtension('js') . '://js';
    if (is_dir($js_dir)) {
      $file_system->scanDirectory($js_dir, '/.*/', ['callback' => $delete_stale]);
    }
  }

}
