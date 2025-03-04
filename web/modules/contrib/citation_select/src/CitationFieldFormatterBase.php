<?php

namespace Drupal\citation_select;

use Drupal\Component\Plugin\PluginBase;
use Drupal\node\Entity\Node;

/**
 * Base plugin for Citation Field Formatters.
 */
class CitationFieldFormatterBase extends PluginBase implements CitationFieldFormatterInterface {

  /**
   * {@inheritdoc}
   */
  public function formatMultiple($node, $node_field, $csl_fields) {
    if (!$node->hasField($node_field) || $node->get($node_field)->isEmpty()) {
      return [];
    }

    $data = [];
    foreach ($csl_fields as $csl_field => $csl_type) {
      if ($csl_type == 'person') {
        $data[$csl_field] = $this->formatNames($this->getFieldValueList($node, $node_field));
      }
      elseif ($csl_type == 'date') {
        $data[$csl_field] = $this->parseDate($this->getField($node, $node_field));
      }
      else {
        $data[$csl_field] = $this->getField($node, $node_field);
      }
    }
    return $data;
  }

  /**
   * Converts date string to CSL-JSON array format.
   *
   * @param string $string
   *   String to format as date.
   *
   * @return array
   *   Date formatted as CSL-JSON
   */
  protected function parseDate($string) {
    $date = date_parse($string);
    return [
      'date-parts' => [[
        $date['year'],
        $date['month'],
        $date['day'],
      ],
      ],
    ];
  }

  /**
   * Gets field value from node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Drupal node object.
   * @param string $field
   *   Field name from node.
   *
   * @return string
   *   Field value from node
   */
  protected function getField(Node $node, $field) {
    return $node->get($field)->getValue()[0]['value'];
  }

  /**
   * Gets list of field values from node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Drupal node object.
   * @param string $field
   *   Field name from node.
   *
   * @return array
   *   List of field values from node
   */
  protected function getFieldValueList(Node $node, $field) {
    $data = array_map(function ($n) {
        return $n['value'];
    }, $node->get($field)->getValue());
    return $data;
  }

  /**
   * Gets list of field values from node.
   *
   * @param array $list
   *   List of strings to format into CSL-JSON format.
   *
   * @return array
   *   List of names formatted as CSL-JSON
   */
  protected function formatNames(array $list) {
    $data = [];
    foreach ($list as $name) {
      $data[] = $this->convertName($name);
    }
    return $data;
  }

  /**
   * Converts string to CSL-JSON list.
   *
   * @param string $name
   *   String to convert into CSL-JSON list.
   *
   * @return array
   *   Name formatted as CSL-JSON
   */
  protected function convertName($name) {
    try {
      $name_parts = \Drupal::service('citation_select.human_name_parser')->parse($name);

      $name_map = [];

      if (isset($name_parts['prefix'])) {
        $name_map['prefix'] = $name_parts['prefix'];
      }
      if (isset($name_parts['first_name'])) {
        $name_map['given'] = $name_parts['first_name'];
      }
      if (isset($name_parts['last_name'])) {
        $name_map['family'] = $name_parts['last_name'];
      }
      if (isset($name_parts['suffix'])) {
        $name_map['suffix'] = $name_parts['suffix'];
      }

      if (count($name_map) == 1) {
        return [
          'literal' => $name,
        ];
      }
      return $name_map;
    }
    catch (Exception $e) {
      return [
        'literal' => $name,
      ];
    }
  }

}
