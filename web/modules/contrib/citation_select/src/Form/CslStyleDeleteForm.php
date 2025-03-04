<?php

namespace Drupal\citation_select\Form;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/Form/CslStyleDeleteForm.php
 * The original code has been modified to fit the needs of this module.
 */

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete CSL style entities.
 */
class CslStyleDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.citation_select_csl_style.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $config = $this->config('citation_select.settings');
    if ($config->get('default_style') == $this->entity->id()) {
      $form_state->setError($form, $this->t('You can not delete default style.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();

    $this->messenger()->addStatus(
      $this->t('content @type: deleted @label.',
        [
          '@type' => $this->entity->bundle(),
          '@label' => $this->entity->label(),
        ]
        )
    );

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
