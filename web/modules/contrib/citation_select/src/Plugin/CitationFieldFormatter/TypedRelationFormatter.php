<?php

namespace Drupal\citation_select\Plugin\CitationFieldFormatter;

use Drupal\citation_select\CitationFieldFormatterBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin to format typed relation field type.
 *
 * @CitationFieldFormatter(
 *    id = "typed_relation",
 *    field_type = "typed_relation",
 * )
 */
class TypedRelationFormatter extends CitationFieldFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Config factory service.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Creates configuration factory member.
   *
   * @param array $configuration
   *   Configuration settings.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin defintion.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
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
   *   Plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formatMultiple($node, $node_field, $csl_fields) {
    $iterator = $node->get($node_field)->getIterator();

    $data = [];
    while ($iterator->valid()) {
      $current = $iterator->current();

      $rel_type = $current->get('rel_type')->getString();
      $rel_name = $this->getTypedRelationsMap($node_field)[$rel_type];

      $entity = $current->get('entity')->getTarget()->getValue();
      $value = $entity->getName();

      if (isset($rel_name) && in_array($rel_name, array_keys($csl_fields))) {
        switch ($csl_fields[$rel_name]) {
          case 'person':
            // islandora-specifific linked agents.
            if ($node_field == 'field_linked_agent') {
              $name_type = $entity->bundle();
              $data[$rel_name][] = ($name_type == 'person') ? $this->convertName($value) : $value;
            }
            else {
              $data[$rel_name][] = $this->convertName($value);
            }
            break;

          default:
            if ($rel_name == "publisher" && isset($data[$rel_name])) {
              break;
            }
            $data[$rel_name] = $value;
            break;
        }
      }
      $iterator->next();
    }
    return $data;
  }

  /**
   * Gets map of typed relation.
   *
   * @param string $node_field
   *   Node field to retrieve map from.
   *
   * @return array
   *   Mapping of typed relation name to corresponding CSL-JSON field.
   */
  protected function getTypedRelationsMap($node_field) {
    $config = $this->configFactory->get('citation_select.settings');
    return $config->get('typed_relation_map');
  }

}
