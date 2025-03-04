<?php

namespace Drupal\citation_select;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/CitationStyler.php
 * The original code has been modified to fit the needs of this module.
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Seboettg\CiteProc\CiteProc;

/**
 * Render CSL data to bibliographic citation.
 */
class CitationStyler implements CitationStylerInterface {


  /**
   * Service configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Storage of CSL style entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $cslStorage;

  /**
   * CSL style entity.
   *
   * @var \Drupal\citation_select\Entity\CslStyleInterface
   */
  protected $style;

  /**
   * Language code.
   *
   * @var string
   */
  protected $langCode;

  /**
   * Styler constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->configuration = $config_factory->get('citation_select.settings');
    $this->languageManager = $language_manager;
    $this->cslStorage = $entity_type_manager->getStorage('citation_select_csl_style');
  }

  /**
   * {@inheritdoc}
   */
  public function render($data) {
    $csl = $this->getStyle()->getCslText();
    $lang = $this->getLanguageCode();
    $cite_proc = new CiteProc($csl, $lang);
    if (!$data instanceof \stdClass) {
      $data = json_decode(json_encode($data));
    }
    return preg_replace('/(\\n|\r)( *)/', '', $cite_proc->render([$data]));
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableStyles() {
    return $this->cslStorage->loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function getStyle() {
    if (!$this->style) {
      $this->setStyleById($this->configuration->get('default_style'));
    }

    return $this->style;
  }

  /**
   * {@inheritdoc}
   */
  public function setStyle($csl_style) {
    $this->style = $csl_style;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStyleById($style_id) {
    $this->style = $this->cslStorage->load($style_id);

    if (!$this->style) {
      throw new \UnexpectedValueException("CSL style '{$style_id}' does not exist.");
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageCode() {
    if (!$this->langCode) {
      $this->langCode = $this->languageManager->getCurrentLanguage()->getId();
    }

    return $this->langCode;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguageCode($lang_code) {
    // @todo M? $this->langCode maybe?
    $this->langCode = $lang_code;
    return $this;
  }

}
