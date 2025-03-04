<?php

/**
 * @file
 * Contains \Drupal\views_nested_details\Plugin\views\style\NestedDetailsStyle.
 */

namespace Drupal\views_nested_details\Plugin\views\style;

use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Details style plugin to render rows as details.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "nested_details",
 *   title = @Translation("Nested Details"),
 *   help = @Translation("Displays rows as details, supports nested groups."),
 *   theme = "views_view_nested_details",
 *   display_types = {"normal"}
 * )
 */
class NestedDetailsStyle extends StylePluginBase {

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Should field labels be enabled by default.
   *
   * @var bool
   */
  protected $defaultFieldLabels = TRUE;

  /**
   * The theme function used to render the grouping set.
   *
   * @var string
   */
  protected $groupingTheme = 'views_view_nested_details_section_grouping';

  /**
   * Render the display in this style.
   */
  public function render() {
    if ($this->usesRowPlugin() && empty($this->view->rowPlugin)) {
      trigger_error('Drupal\views\Plugin\views\style\StylePluginBase: Missing row plugin', E_WARNING);
      return [];
    }

    // Group the rows according to the grouping instructions, if specified.
    $sets = $this->renderGrouping(
      $this->view->result,
      $this->options['grouping'],
      TRUE
    );

    return $this->renderGroupingSets($sets);
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();
    if (!$this->usesFields()) {
      $errors[] = $this->t('Views Nested Details requires Fields as row style');
    }

    foreach ($this->options['grouping'] as $group) {
      if (!$group['rendered']) {
        $errors[] = $this->t('Views Nested Details requires "Use rendered output to group rows" enabled in order to use the field values for grouping.');
      }
      // @TODO handle multiple grouping.
      break;
    }

    return $errors;

  }

}
