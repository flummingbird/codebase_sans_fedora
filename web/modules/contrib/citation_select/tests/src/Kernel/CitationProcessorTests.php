<?php

namespace Drupal\Tests\citation_select\Kernel;

use Drupal\citation_select\HumanNameParser;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests citation processor service.
 *
 * @group citation_select
 */
class CitationProcessorTests extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'citation_select',
    'taxonomy',
    'field',
    'node',
    'filter',
    'user',
    'system',
    'text',
  ];

  /**
   * Default formatter.
   *
   * @var CitationProcessorService
   */
  protected $citationProcessor;

  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');

    $this->installConfig('citation_select');

    $vocabulary = Vocabulary::create([
      'name' => 'term1',
      'vid' => 'term1',
    ]);
    $vocabulary->save();

    $node_type = NodeType::create([
      'type' => 'repository_object',
      'name' => 'Repository object',
      'description' => "Repository object for testing.",
    ]);
    $node_type->save();

    // Unlimited => true.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'text_field',
      'entity_type' => 'node',
      'type' => 'text',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'text_field',
      'entity_type' => 'node',
      'bundle' => 'repository_object',
      'label' => 'Test text field',
    ]);
    $field->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'text_date_field',
      'entity_type' => 'node',
      'type' => 'text',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'text_date_field',
      'entity_type' => 'node',
      'bundle' => 'repository_object',
      'label' => 'Test date field',
    ]);
    $field->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'entity_reference_field',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'entity_reference_field',
      'entity_type' => 'node',
      'bundle' => 'repository_object',
      'label' => 'Test taxonomy field',
    ]);
    $field->save();

    $this->configFactory = $this->container->get('config.factory');
    $this->citationProcessor = $this->container->get('citation_select.citationProcessor');

    $human_parser_mock = $this->getMockBuilder(HumanNameParser::class)
      ->disableOriginalConstructor()
      ->getMock();
    $human_parser_mock->expects($this->any())
      ->method('parse')
      ->will($this->returnCallback(
        function ($x) {
          if ($x == 'John') {
            return ['first_name' => 'John'];
          }
          if ($x == 'John Smith') {
            return ['first_name' => 'John', 'last_name' => 'Smith'];
          }
          if ($x == 'Jane Smith') {
            return ['first_name' => 'Jane', 'last_name' => 'Smith'];
          }
        }
      ));

    $this->container->set('citation_select.human_name_parser', $human_parser_mock);
  }

  /**
   * Test reference type is correct.
   */
  public function testReferenceType() {
    $this->configFactory->getEditable('citation_select.settings')
      ->set('csl_map', [])
      ->save();
    // No type set.
    $obj = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'book',
      'nid' => 11,
    ]);
    $obj->save();
    $citation_array = $this->citationProcessor->getCitationArray(11);
    $this->assertEquals('document', $citation_array['type']);

    $this->configFactory->getEditable('citation_select.settings')
      ->set(
      'csl_map',
      [
        'text_field' => [
          'type',
        ],
      ])
      ->save();

    // No mapping: valid.
    $obj = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'book',
      'nid' => 10,
    ]);
    $obj->save();
    $citation_array = $this->citationProcessor->getCitationArray(10);
    $this->assertEquals('book', $citation_array['type']);

    // Tests using mapping.
    $this->configFactory->getEditable('citation_select.settings')
      ->set(
      'reference_type_field_map',
      [
        'paged content' => 'book',
      ])
      ->save();

    // Type invalid.
    $obj = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'abcdef',
      'nid' => 5,
    ]);
    $obj->save();
    $citation_array = $this->citationProcessor->getCitationArray(5);
    $this->assertEquals('document', $citation_array['type']);

    // Type valid.
    $obj = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'book',
      'nid' => 6,
    ]);
    $obj->save();
    $citation_array = $this->citationProcessor->getCitationArray(6);
    $this->assertEquals('book', $citation_array['type']);

    // Map type.
    $obj = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'Paged Content',
      'nid' => 7,
    ]);
    $obj->save();
    $citation_array = $this->citationProcessor->getCitationArray(7);
    $this->assertEquals('book', $citation_array['type']);
  }

  /**
   * Test formatting.
   */
  public function testFormatting() {
    $this->configFactory->getEditable('citation_select.settings')
      ->set(
        'csl_map',
        [
          'title' => [
            'title',
          ],
          'text_field' => [
            'author',
            'publisher',
          ],
          'text_date_field' => [
            'issued',
          ],
          'entity_reference_field' => [
            'genre',
          ],
          'fake_field' => [
            'note',
          ],
        ])
      ->save();

    $term = Term::create([
      'name' => 'book',
      'vid' => 'term1',
      'tid' => 1,
    ]);
    $term->save();
    $obj = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => ['John Smith', 'Jane Smith'],
      'text_date_field' => '2022/01/01',
      'entity_reference_field' => [
        ['target_id' => 1],
      ],
      'nid' => 12,
    ]);
    $obj->save();
    $citation_array = $this->citationProcessor->getCitationArray(12);
    $this->assertEquals(
      [
        'author' => [
          [
            'given' => 'John',
            'family' => 'Smith',
          ],
          [
            'given' => 'Jane',
            'family' => 'Smith',
          ],
        ],
        'title' => 'Title',
        'type' => 'document',
        'issued' => [
          'date-parts' => [
            [
              2022,
              01,
              01,
            ],
          ],
        ],
        'genre' => 'book',
        'publisher' => 'John Smith',
      ],
      $citation_array
    );
  }

}
