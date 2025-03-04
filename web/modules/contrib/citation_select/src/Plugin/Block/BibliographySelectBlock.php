<?php

namespace Drupal\citation_select\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to generate citations from content.
 *
 * @Block(
 *   id = "citation_select_block",
 *   admin_label = @Translation("Citation Select Block"),
 *   category = @Translation("Citation Select")
 * )
 */
class BibliographySelectBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Placeholder.
   *
   * @param array $configuration
   *   Configuration settings.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin defintion.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Form builder member variable.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

  /**
   * Constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container for dependency injection.
   * @param array $configuration
   *   Configuration settings.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin defintion.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['form'] = $this->formBuilder->getForm('Drupal\citation_select\Form\SelectCitationForm');
    return $build;
  }

}
