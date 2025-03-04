<?php

namespace Drupal\Tests\citation_select\Kernel;

use Drupal\citation_select\HumanNameParser;
use Drupal\citation_select\Plugin\CitationFieldFormatter\DefaultCitationFieldFormatter;
use Drupal\citation_select\Plugin\CitationFieldFormatter\EntityReferenceFormatter;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Plugin\PluginTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests field plugins.
 *
 * @group citation_select
 */
class CitationPluginTests extends PluginTestBase {

  /**
   * Module list.
   *
   * @var arraystring
   */
  protected static $modules = [
    'citation_select',
    'node',
    'user',
    'taxonomy',
    'text',
    'system',
    'field',
  ];

  /**
   * Default formatter.
   *
   * @var Drupal\citation_select\Plugin\CitationFieldFormatter\DefaultCitationFieldFormatter
   */
  protected $defaultFormatter;

  /**
   * Entity reference formatter.
   *
   * @var Drupal\citation_select\Plugin\CitationFieldFormatter\EntityReferenceFormatter
   */
  protected $entityReferenceFormatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');

    $vocabulary = Vocabulary::create([
      'name' => 'term1',
      'vid' => 'term1',
    ]);
    $vocabulary->save();
    $term = Term::create([
      'name' => 'John Smith',
      'vid' => 'term1',
      'tid' => 1,
    ]);
    $term->save();
    $term = Term::create([
      'name' => 'John',
      'vid' => 'term1',
      'tid' => 2,
    ]);
    $term->save();

    $node_type = NodeType::create([
      'type' => 'repository_object',
      'name' => 'Repository object',
      'description' => "Repository object for testing.",
    ]);
    $node_type->save();

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
      'field_name' => 'entity_reference_field',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'entity_reference_field',
      'entity_type' => 'node',
      'bundle' => 'repository_object',
      'label' => 'Test taxonomy field',
    ]);
    $field->save();

    $this->citation_processor = $this->container->get('citation_select.citation_processor');

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

    $this->defaultFormatter = new DefaultCitationFieldFormatter([], 'default', []);
    $this->entity_formatter = new EntityReferenceFormatter([], 'entity_reference', []);
  }

  /**
   * Test default entity reference formatter.
   */
  public function testEntityReference() {
    $node = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'entity_reference_field' => [
        ['target_id' => 2],
      ],
    ]);
    $node->save();

    // One standard.
    $result = $this->entity_formatter->formatMultiple($node, 'entity_reference_field', ['genre' => 'standard']);
    $this->assertEquals(['genre' => 'John'], $result);

    $node = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'entity_reference_field' => [
        ['target_id' => 1],
        ['target_id' => 2],
      ],
    ]);
    $node->save();

    // Multiple names.
    $result = $this->entity_formatter->formatMultiple($node, 'entity_reference_field', ['genre' => 'person']);

    $this->assertEquals(
      [
        'genre' => [
          [
            'given' => 'John',
            'family' => 'Smith',
          ],
          [
            'literal' => 'John',
          ],
        ],
      ],
      $result
    );
  }

  /**
   * Test default field formatter.
   */
  public function testDefault() {
    $node = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'Text',
    ]);
    $node->save();

    // Title works.
    $result = $this->defaultFormatter->formatMultiple($node, 'title', ['title' => 'standard']);
    $this->assertEquals(['title' => 'Title'], $result);

    // Standard.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field', ['genre' => 'standard']);
    $this->assertEquals(['genre' => 'Text'], $result);

    // More fields, standard.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field',
      [
        'genre' => 'standard',
        'publisher' => 'standard',
      ]
    );
    $this->assertEquals(
      [
        'genre' => 'Text',
        'publisher' => 'Text',
      ],
      $result
    );

    $node = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => '2022/01/31',
    ]);
    $node->save();

    // Date.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field', ['genre' => 'date']);
    $this->assertEquals(
      [
        'genre' => [
          'date-parts' => [
          [
            2022,
            01,
            31,
          ],
          ],
        ],
      ],
      $result
    );

    $node = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => 'John',
    ]);
    $node->save();

    // Name.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field', ['genre' => 'person']);
    $this->assertEquals(
      [
        'genre' => [
          [
            'literal' => 'John',
          ],
        ],
      ],
      $result
    );

    // Name + other kind of field.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field',
      [
        'genre' => 'person',
        'publisher' => 'standard',
      ]
    );
    $this->assertEquals(
      [
        'genre' => [
          [
            'literal' => 'John',
          ],
        ],
        'publisher' => 'John',
      ],
      $result
    );

    $node = Node::create([
      'type' => 'repository_object',
      'title' => 'Title',
      'text_field' => ['John', 'John Smith'],
    ]);
    $node->save();
    // Names.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field', ['genre' => 'person']);
    $this->assertEquals(
      [
        'genre' => [
          [
            'literal' => 'John',
          ],
          [
            'given' => 'John',
            'family' => 'Smith',
          ],
        ],
      ],
      $result
    );

    // Multiple standard.
    $result = $this->defaultFormatter->formatMultiple($node, 'text_field', ['genre' => 'standard']);
    $this->assertEquals(['genre' => 'John'], $result);

    // Field DNE.
    $result = $this->defaultFormatter->formatMultiple($node, 'abcdef', ['genre' => 'standard']);
    $this->assertEquals([], $result);
  }

}
