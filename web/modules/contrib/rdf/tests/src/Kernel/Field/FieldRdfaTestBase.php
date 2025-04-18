<?php

namespace Drupal\Tests\rdf\Kernel\Field;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\rdf\Traits\RdfParsingTrait;

/**
 * Abstract class for testing RDF fields.
 */
abstract class FieldRdfaTestBase extends FieldKernelTestBase {

  use RdfParsingTrait;

  /**
   * The machine name of the field type to test.
   *
   * @var string
   */
  protected string $fieldType;

  /**
   * The name of the field to create for testing.
   *
   * @var string
   */
  protected string $fieldName = 'field_test';

  /**
   * The URI to identify the entity.
   *
   * @var string
   */
  protected string $uri = 'http://ex.com';

  /**
   * The entity to render for testing.
   *
   * @var \Drupal\Core\Entity\ContentEntityBase
   */
  protected $entity;

  /**
   * TRUE if verbose debugging is enabled.
   *
   * @var bool
   */
  protected bool $debug = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['rdf'];

  /**
   * Test value.
   *
   * @var string
   */
  protected string $testValue;

  /**
   * Helper function to test the formatter's RDFa.
   *
   * @param array $formatter
   *   An associative array describing the formatter to test and its settings
   *   containing:
   *   - type: The machine name of the field formatter to test.
   *   - settings: The settings of the field formatter to test.
   * @param string $property
   *   The property that should be found.
   * @param array $expected_rdf_value
   *   An associative array describing the expected value of the property
   *   containing:
   *   - value: The actual value of the string or URI.
   *   - type: The type of RDF value, e.g. 'literal' for a string, or 'uri'.
   *   Defaults to 'literal'.
   *   - datatype: (optional) The datatype of the value (e.g. xsd:dateTime).
   */
  protected function assertFormatterRdfa(array $formatter, string $property, array $expected_rdf_value): void {
    $expected_rdf_value += ['type' => 'literal'];

    // The field formatter will be rendered inside the entity. Set the field
    // formatter in the entity display options before rendering the entity.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($this->fieldName, $formatter)
      ->save();
    $build = \Drupal::entityTypeManager()
      ->getViewBuilder($this->entity->getEntityTypeId())
      ->view($this->entity, 'default');
    $output = \Drupal::service('renderer')->renderRoot($build);
    $this->setRawContent($output);
    $this->assertTrue($this->hasRdfProperty($output, $this->uri, $this->uri, $property, $expected_rdf_value), "Formatter {$formatter['type']} exposes data correctly for {$this->fieldType} fields.");
  }

  /**
   * Creates the field for testing.
   *
   * @param array $field_settings
   *   (optional) An array of field settings.
   */
  protected function createTestField(array $field_settings = []): void {
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'type' => $this->fieldType,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => $this->fieldName,
      'bundle' => 'entity_test',
      'settings' => $field_settings,
    ])->save();
  }

  /**
   * Gets the absolute URI of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity for which to generate the URI.
   *
   * @return string
   *   The absolute URI.
   */
  protected function getAbsoluteUri(ContentEntityBase $entity): string {
    return $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
  }

  /**
   * Parses a content and return the html element.
   *
   * @param string $content
   *   The html to parse.
   *
   * @return array
   *   An array containing simplexml objects.
   */
  protected function parseContent(string $content) {
    $htmlDom = new \DOMDocument();
    @$htmlDom->loadHTML('<?xml encoding="UTF-8">' . $content);
    return simplexml_import_dom($htmlDom);
  }

  /**
   * Performs a xpath search on a certain content.
   *
   * The search is relative to the root element of the $content variable.
   *
   * @param string $content
   *   The html to parse.
   * @param string $xpath
   *   The xpath string to use in the search.
   * @param array $arguments
   *   Some arguments for the xpath.
   *
   * @return array|false
   *   The return value of the xpath search. For details on the xpath string
   *   format and return values see the SimpleXML documentation,
   *   http://php.net/manual/function.simplexml-element-xpath.php.
   */
  protected function xpathContent(string $content, string $xpath, array $arguments = []) {
    if ($elements = $this->parseContent($content)) {
      $xpath = $this->buildXPathQuery($xpath, $arguments);
      $result = $elements->xpath($xpath);
      // Some combinations of PHP / libxml versions return an empty array
      // instead of the documented FALSE. Forcefully convert any falsish values
      // to an empty array to allow foreach(...) constructions.
      return $result ? $result : [];
    }
    else {
      return FALSE;
    }
  }

}
