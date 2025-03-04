<?php

namespace Drupal\citation_select;

use Drupal\citation_select\Annotation\CitationFieldFormatter;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for custom Citation Field Formatter plugin type.
 */
class CitationFieldFormatterPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct($namespaces, $cache_backend, $module_handler) {
    $subdir = 'Plugin/CitationFieldFormatter';
    $plugin_interface = CitationFieldFormatterInterface::class;
    $plugin_definition_annotation_name = CitationFieldFormatter::class;
    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);
    $this->alterInfo('citation_select_info');
    $this->setCacheBackend($cache_backend, 'citation_select_info_plugins');
  }

}
