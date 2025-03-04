<?php

namespace Drupal\citation_select;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/CitationStylerInterface.php
 * The original code has been modified to fit the needs of this module.
 */

/**
 * Defines an interface for Styler service.
 */
interface CitationStylerInterface {

  /**
   * Render CSL data to bibliographic citation.
   *
   * @param array|\stdClass $data
   *   Array or object of values in CSL format.
   *
   * @return string
   *   Rendered bibliographic citation.
   */
  public function render($data);

  /**
   * Get current CSL style.
   *
   * @return \Drupal\citation_select\Entity\CslStyleInterface|null
   *   Current CSL style.
   */
  public function getStyle();

  /**
   * Set CSL style.
   *
   * @param \Drupal\citation_select\Entity\CslStyleInterface|null $csl_style
   *   CSL style object or NULL to reset to default style.
   *
   * @return $this
   *
   * @todo Use nullable type hint when Drupal will drop PHP 7.0 support.
   */
  public function setStyle($csl_style);

  /**
   * Load and set style by identifier.
   *
   * @param string $style_id
   *   CSL style identifier.
   *
   * @return \Drupal\citation_select\CitationStylerInterface
   *   The called Styler object.
   */
  public function setStyleById($style_id);

  /**
   * Get list of available bibliographic styles.
   *
   * @return array
   *   Bibliographic styles list.
   */
  public function getAvailableStyles();

  /**
   * Get current used language code.
   *
   * @return string
   *   Current language code.
   */
  public function getLanguageCode();

  /**
   * Set language code.
   *
   * @param string $lang_code
   *   Language code.
   *
   * @return \Drupal\citation_select\CitationStylerInterface
   *   The called Styler object.
   */
  public function setLanguageCode($lang_code);

}
