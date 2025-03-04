<?php

namespace Drupal\citation_select\Entity;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/Entity/CslStyleInterface.php
 * The original code has been modified to fit the needs of this module.
 */

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining CSL style entities.
 */
interface CslStyleInterface extends ConfigEntityInterface {

  /**
   * Check if this style has a prent style.
   *
   * @return bool
   *   Boolean indicate the existence of parent.
   */
  public function hasParent();

  /**
   * Get parent style.
   *
   * @return \Drupal\citation_select\Entity\CslStyleInterface|null
   *   Parent style object or NULL.
   */
  public function getParent();

  /**
   * Set parent style.
   *
   * @param \Drupal\citation_select\Entity\CslStyleInterface|null $parent
   *   Parent style object or NULL if you want to delete relationship.
   *
   * @return $this
   */
  public function setParent($parent = NULL);

  /**
   * Get text of CSL style.
   *
   * @return string
   *   CSL text
   */
  public function getCslText();

  /**
   * Set text of CSL style.
   *
   * @param string $csl_text
   *   The new CSL text.
   *
   * @return $this
   */
  public function setCslText($csl_text);

  /**
   * Get date of the latest update.
   *
   * @return int
   *   Timestamp of the latest update.
   */
  public function getUpdatedTime();

  /**
   * Set a new updated time.
   *
   * @param int $timestamp
   *   Timestamp of updated time.
   *
   * @return $this
   */
  public function setUpdatedTime($timestamp);

  /**
   * Calculate hash from CSL text.
   *
   * @return string
   *   Hash string calculated from CSL text.
   */
  public function calculateHash();

  /**
   * Check if this CSL style is custom.
   *
   * @return bool
   *   TRUE if this CSL style marked as custom and FALSE if not.
   */
  public function isCustom();

  /**
   * Set custom flag for this CSL style.
   *
   * @param bool $custom
   *   The custom flag.
   *
   * @return $this
   */
  public function setCustom($custom);

  /**
   * Get the URL identifier of the style.
   *
   * @return string|null
   *   URL string or NULL.
   */
  public function getUrlId();

  /**
   * Set the URL identifier.
   *
   * @param string $url_id
   *   URL identifier string.
   *
   * @return $this
   */
  public function setUrlId($url_id);

}
