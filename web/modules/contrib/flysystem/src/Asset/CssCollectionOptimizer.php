<?php

namespace Drupal\flysystem\Asset;

use Drupal\Core\Asset\CssCollectionOptimizer as DrupalCssCollectionOptimizer;
use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * Optimizes CSS assets.
 */
class CssCollectionOptimizer extends DrupalCssCollectionOptimizer {// @phpstan-ignore-line @codingStandardsIgnoreLine 

  use SchemeExtensionTrait;
  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->state->delete('drupal_css_cache_files');
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
    $css_dir = $this->getSchemeForExtension('css') . '://css';
    if (is_dir($css_dir)) {
      $file_system->scanDirectory($css_dir, '/.*/', ['callback' => $delete_stale]);
    }
  }

}
