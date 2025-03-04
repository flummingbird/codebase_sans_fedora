<?php

namespace Drupal\taxonomy_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller routines for taxonomy_manager routes.
 */
class MainController extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a MainController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(Request $request) {
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * List of vocabularies, which link to Taxonomy Manager interface.
   *
   * @return array
   *   A render array representing the page.
   */
  public function listVocabularies() {
    $build = [];

    $voc_list = [];
    $vocabularies = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      if ($this->entityTypeManager()->getAccessControlHandler('taxonomy_term')->createAccess($vocabulary->id())) {
        $vocabulary_form = Url::fromRoute('taxonomy_manager.admin_vocabulary',
          ['taxonomy_vocabulary' => $vocabulary->id()]);
        $voc_list[] = ['data' => [Link::fromTextAndUrl($vocabulary->label(), $vocabulary_form)]];
      }
    }

    if (!count($voc_list)) {
      $voc_list[] = ['#markup' => $this->t('No Vocabularies available')];
    }

    $header = ['Vocabularies'];

    $build['vocabularies'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $voc_list,
    ];

    return $build;
  }

  /**
   * Render Taxonomy Term form.
   *
   * @return array
   *   A render array representing the page.
   */
  public function getTaxonomyManagerForm($taxonomy_vocabulary) {
    $build = [];
    $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($taxonomy_vocabulary);

    $build['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'taxonomy-manager-wrapper', 'class' => ['taxonomy-manager-wrapper']],
    ];

    $build['wrapper']['form'] = $this->formBuilder()->getForm('\Drupal\taxonomy_manager\Form\TaxonomyManagerForm', $vocabulary);

    $tid = $this->request->query->get('tid');
    $tidValue = (!empty($tid) && is_numeric($tid)) ? $tid : NULL;

    if ($tidValue) {
      $build['wrapper']['term_edit'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Term Form'),
        '#title_display' => 'invisible',
        '#attributes' => ['id' => 'wrapper-term-edit'],
      ];
      $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($tidValue);
      $build['wrapper']['term_edit']['form'] = $this->entityFormBuilder()->getForm($term);
    }

    return $build;
  }

  /**
   * Returns the title for the whole page.
   *
   * @param object $taxonomy_vocabulary
   *   The name of the vocabulary.
   *
   * @return string
   *   The title, itself
   */
  public function getAdminVocabularyTitle($taxonomy_vocabulary) {
    $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($taxonomy_vocabulary);
    return $this->t("Taxonomy Manager - %voc_name", ["%voc_name" => $vocabulary->label()]);
  }

}
