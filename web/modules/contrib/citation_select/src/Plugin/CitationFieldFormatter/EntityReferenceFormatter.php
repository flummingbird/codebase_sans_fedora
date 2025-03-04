<?php

namespace Drupal\citation_select\Plugin\CitationFieldFormatter;

use Drupal\citation_select\CitationFieldFormatterBase;

/**
 * Plugin to format entity reference field type.
 *
 * @CitationFieldFormatter(
 *    id = "entity_reference",
 *    field_type = "entity_reference",
 * )
 */
class EntityReferenceFormatter extends CitationFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function getField($node, $field) {
    return $node->get($field)->referencedEntities()[0]->getName();
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldValueList($node, $field) {
    $data = array_map(
      function ($n) {
        return $n->getName();
      }, $node->get($field)->referencedEntities()
    );
    return $data;
  }

}
