<?php

namespace Drupal\citation_select\Form;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/Form/SettingsForm.php
 * The original code has been modified to fit the needs of this module.
 */

use Drupal\citation_select\CitationStylerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common configuration.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The styler service.
   *
   * @var \Drupal\citation_select\CitationStylerInterface
   */
  protected $styler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, CitationStylerInterface $styler) {
    parent::__construct($config_factory);
    $this->styler = $styler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('citation_select.citation_styler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'citation_select_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'citation_select.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('citation_select.settings');

    $csl_styles = $this->styler->getAvailableStyles();
    $styles_options = array_map(function ($entity) {
      /** @var \Drupal\citation_select\Entity\CslStyleInterface $entity */
      return $entity->label();
    }, $csl_styles);

    $form['default_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Default style'),
      '#options' => $styles_options,
      '#default_value' => $config->get('default_style'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('citation_select.settings');
    $config
      ->set('default_style', $form_state->getValue('default_style'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
