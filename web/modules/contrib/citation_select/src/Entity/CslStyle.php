<?php

namespace Drupal\citation_select\Entity;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/Entity/CslStyle.php
 * The original code has been modified to fit the needs of this module.
 */

use Drupal\citation_select\Csl;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the CSL style entity.
 *
 * @ConfigEntityType(
 *   id = "citation_select_csl_style",
 *   label = @Translation("CSL style"),
 *   handlers = {
 *     "list_builder" = "Drupal\citation_select\CslStyleListBuilder",
 *     "form" = {
 *       "add" = "Drupal\citation_select\Form\CslStyleForm",
 *       "add-file" = "Drupal\citation_select\Form\CslStyleFileForm",
 *       "edit" = "Drupal\citation_select\Form\CslStyleForm",
 *       "delete" = "Drupal\citation_select\Form\CslStyleDeleteForm"
 *     },
 *   },
 *   config_prefix = "citation_select_csl_style",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/citation_select/csl_style/add",
 *     "add-form-file" = "/admin/config/citation_select/csl_style/add-file",
 *     "edit-form" = "/admin/config/citation_select/csl_style/{citation_select_csl_style}",
 *     "delete-form" = "/admin/config/citation_select/csl_style/{citation_select_csl_style}/delete",
 *     "collection" = "/admin/config/citation_select/csl_style"
 *   },
 *   config_export = {
 *     "id",
 *     "parent",
 *     "label",
 *     "csl",
 *     "updated",
 *     "custom",
 *     "url_id",
 *     "override",
 *     "preview_mode",
 *     "citekey_pattern",
 *     "fields",
 *   }
 * )
 */
class CslStyle extends ConfigEntityBase implements CslStyleInterface {

  /**
   * The CSL style ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The parent style ID.
   *
   * @var string
   */
  protected $parent = NULL;

  /**
   * The CSL style label.
   *
   * @var string
   */
  protected $label;

  /**
   * The text of CSL.
   *
   * @var string
   */
  protected $csl;

  /**
   * The time of latest update.
   *
   * @var int
   */
  protected $updated;

  /**
   * Indicated that style installed by user from text or file.
   *
   * @var bool
   */
  protected $custom = TRUE;

  /**
   * The URL of the style used as identifier in CSL ecosystem.
   *
   * @var string
   */
  protected $url_id;

  /**
   * {@inheritdoc}
   */
  public function getCslText() {
    return $this->csl;
  }

  /**
   * {@inheritdoc}
   */
  public function setCslText($csl_text) {
    $this->csl = $csl_text;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime() {
    return $this->updated;
  }

  /**
   * {@inheritdoc}
   */
  public function setUpdatedTime($timestamp) {
    $this->updated = $timestamp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateHash() {
    $xml = simplexml_load_string($this->csl);
    return hash('sha256', $xml->asXML());
  }

  /**
   * {@inheritdoc}
   */
  public function isCustom() {
    return $this->custom;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustom($custom) {
    $this->custom = (bool) $custom;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasParent() {
    return !empty($this->parent);
  }

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    return $this->parent ? static::load($this->parent) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setParent($parent = NULL) {
    $this->parent = ($parent instanceof CslStyleInterface)
      ? $this->parent = $parent->id()
      : $this->parent = $parent;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlId() {
    return $this->url_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrlId($url_id) {
    $this->url_id = $url_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $csl = new Csl($this->csl);

    if ($url_id = $csl->getId()) {
      $this->setUrlId($url_id);
    }

    if ($parent_id = $csl->getParent()) {
      $storage = $this->entityTypeManager()->getStorage($this->getEntityTypeId());

      $result = $storage->getQuery()
        ->accessCheck()
        ->condition('url_id', $parent_id)
        ->execute();

      if (!$result) {
        throw new \Exception("CSL style cannot be saved without installed parent style.");
      }

      $parent_internal_id = reset($result);
      $this->setParent($parent_internal_id);
    }
  }

}
