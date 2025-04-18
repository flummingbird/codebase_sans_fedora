<?php

namespace Drupal\Tests\rdf\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests RDFa attribute generation from RDF mapping.
 *
 * @group rdf
 */
class RdfaAttributesTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['rdf'];

  /**
   * Tests attribute creation for mappings which use 'property'.
   */
  public function testProperty() {
    $properties = ['dc:title'];

    $mapping = ['properties' => $properties];
    $expected_attributes = ['property' => $properties];

    $this->testAttributes($expected_attributes, $mapping);
  }

  /**
   * Tests attribute creation for mappings which use 'datatype'.
   */
  public function testDatatype() {
    $properties = ['foo:bar1'];
    $datatype = 'foo:bar1type';

    $mapping = [
      'datatype' => $datatype,
      'properties' => $properties,
    ];
    $expected_attributes = [
      'datatype' => $datatype,
      'property' => $properties,
    ];

    $this->testAttributes($expected_attributes, $mapping);
  }

  /**
   * Tests attribute creation for mappings that override human-readable content.
   */
  public function testDatatypeCallback() {
    $properties = ['dc:created'];
    $datatype = 'xsd:dateTime';

    $date = 1252750327;
    $iso_date = $this->container->get('date.formatter')->format($date, 'custom', 'c', 'UTC');

    $mapping = [
      'datatype' => $datatype,
      'properties' => $properties,
      'datatype_callback' => ['callable' => 'Drupal\rdf\CommonDataConverter::dateIso8601Value'],
    ];
    $expected_attributes = [
      'datatype' => $datatype,
      'property' => $properties,
      'content' => $iso_date,
    ];

    $this->testAttributes($expected_attributes, $mapping, ['value' => $date]);
  }

  /**
   * Tests attribute creation for mappings which use data converters.
   */
  public function testDatatypeCallbackWithConverter() {
    $properties = ['schema:interactionCount'];

    $data = "23";
    $content = "UserComments:23";

    $mapping = [
      'properties' => $properties,
      'datatype_callback' => [
        'callable' => 'Drupal\rdf\SchemaOrgDataConverter::interactionCount',
        'arguments' => ['interaction_type' => 'UserComments'],
      ],
    ];
    $expected_attributes = [
      'property' => $properties,
      'content' => $content,
    ];

    $this->testAttributes($expected_attributes, $mapping, $data);
  }

  /**
   * Tests attribute creation for mappings which use 'rel'.
   */
  public function testRel() {
    $properties = ['sioc:has_creator', 'dc:creator'];

    $mapping = [
      'properties' => $properties,
      'mapping_type' => 'rel',
    ];
    $expected_attributes = ['rel' => $properties];

    $this->testAttributes($expected_attributes, $mapping);
  }

  /**
   * Helper function to test attribute generation.
   *
   * @param array $expected_attributes
   *   The expected return of rdf_rdfa_attributes.
   * @param array $field_mapping
   *   The field mapping to merge into the RDF mapping config.
   * @param mixed|null $data
   *   The data to pass into the datatype callback, if specified.
   */
  protected function testAttributes(array $expected_attributes, array $field_mapping, mixed $data = NULL): void {
    $mapping = rdf_get_mapping('node', 'article')
      ->setFieldMapping('field_test', $field_mapping)
      ->getPreparedFieldMapping('field_test');
    $attributes = rdf_rdfa_attributes($mapping, $data);
    ksort($expected_attributes);
    ksort($attributes);
    $this->assertEquals($expected_attributes, $attributes);
  }

}
