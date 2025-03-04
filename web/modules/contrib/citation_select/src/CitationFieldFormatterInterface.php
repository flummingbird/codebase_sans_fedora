<?php

namespace Drupal\citation_select;

use Drupal\node\Entity\Node;

/**
 * Interface for custom plugin type Citation Field Formatter.
 */
interface CitationFieldFormatterInterface {

  /**
   * Returns array of data from $node_field of $node using $csl_fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Drupal node object.
   * @param string $node_field
   *   Name of node field to retrieve data from.
   * @param array $csl_fields
   *   Maps name of CSL-JSON field to 'type' (standard, person, date)
   *
   * @return array
   *   Formatted array of data from $node_field of $node using $csl_fields
   */
  public function formatMultiple(Node $node, $node_field, array $csl_fields);

}
