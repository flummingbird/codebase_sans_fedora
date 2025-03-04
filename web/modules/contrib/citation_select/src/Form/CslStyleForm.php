<?php

namespace Drupal\citation_select\Form;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/Form/CslStyleForm.php
 * The original code has been modified to fit the needs of this module.
 */

use Drupal\citation_select\Csl;
use Drupal\citation_select\Entity\CslStyle;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add/edit form for citation_select_csl_style entity.
 */
class CslStyleForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\citation_select\Entity\CslStyleInterface $csl_style */
    $csl_style = $this->getEntity();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $csl_style->label(),
      '#description' => $this->t("Label for the CSL style."),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $csl_style->id(),
      '#machine_name' => [
        'exists' => [CslStyle::class, 'load'],
      ],
      '#disabled' => !$csl_style->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $csl_style->status(),
      '#access' => !$csl_style->isNew(),
    ];

    $form['csl'] = [
      '#type' => 'textarea',
      '#rows' => 20,
      '#title' => $this->t('CSL text'),
      '#default_value' => $csl_style->getCslText(),
      '#required' => TRUE,
    ];

    $form['url_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#access' => !$csl_style->isNew(),
      '#default_value' => $csl_style->getUrlId(),
      '#disabled' => TRUE,
    ];

    $form['parent'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Parent style'),
      '#target_type' => 'citation_select_csl_style',
      '#default_value' => $csl_style->getParent(),
      '#access' => !$csl_style->isNew(),
      '#disabled' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\citation_select\Entity\CslStyleInterface $csl_style */
    $csl_style = $this->getEntity();
    $csl = new Csl($form_state->getValue('csl'));

    if ($csl_style->isNew()) {
      $this->validateUnique($form, $form_state, $csl->getId());

      if ($parent_url = $csl->getParent()) {
        $this->validateParent($form, $form_state, $parent_url);
      }
    }

    if (!$csl_style->isNew()) {
      /** @var \Drupal\citation_select\Entity\CslStyleInterface $original_csl_style */
      $original_csl_style = $this->entityTypeManager->getStorage($csl_style->getEntityTypeId())->load($csl_style->id());

      if ($csl_style->calculateHash() != $original_csl_style->calculateHash()) {
        if ($original_csl_style->getUrlId() != $csl->getId()) {
          $this->validateUnique($form, $form_state, $csl->getId());
        }

        if ($parent_url = $csl->getParent()) {
          $this->validateParent($form, $form_state, $parent_url);
        }
      }

      $default_style = $this->config('citation_select.settings')->get('default_style');
      if (!$csl_style->status() && $default_style == $csl_style->id()) {
        $form_state->setErrorByName('status', $this->t('You can not disable default style.'));
      }
    }
  }

  /**
   * Find CSL styles by URL ID property.
   *
   * @param string $url_id
   *   URL ID property.
   *
   * @return array
   *   List of found CSL styles.
   */
  protected function findStyleByUrlId($url_id) {
    $storage = $this->entityTypeManager->getStorage('citation_select_csl_style');

    $result = $storage->getQuery()
      ->accessCheck()
      ->condition('url_id', $url_id)
      ->execute();

    return $result;
  }

  /**
   * Validate unique URl ID property.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $url_id
   *   URL ID property.
   */
  protected function validateUnique(array &$form, FormStateInterface $form_state, $url_id) {
    if ($result = $this->findStyleByUrlId($url_id)) {
      $form_state->setError($form, $this->t('You are trying to save existing style. Check out style with this id: @id', ['@id' => reset($result)]));
    }
  }

  /**
   * Validate existing of parent style.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $parent_url
   *   URL ID of parent style.
   */
  protected function validateParent(array &$form, FormStateInterface $form_state, $parent_url) {
    if (!$this->findStyleByUrlId($parent_url)) {
      $message = $this->t('You are trying to save dependent style without installed parent. You should install parent style first: @style', [
        '@style' => $parent_url,
      ]);
      $form_state->setError($form, $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\citation_select\Entity\CslStyle $citation_select_csl_style */
    $citation_select_csl_style = $this->entity;
    $citation_select_csl_style->setUpdatedTime(time());
    $status = $citation_select_csl_style->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label CSL style.', [
          '%label' => $citation_select_csl_style->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label CSL style.', [
          '%label' => $citation_select_csl_style->label(),
        ]));
    }

    $form_state->setRedirectUrl($citation_select_csl_style->toUrl('collection'));
  }

}
