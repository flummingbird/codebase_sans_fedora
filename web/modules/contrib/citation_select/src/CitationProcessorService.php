<?php

namespace Drupal\citation_select;

use Drupal\node\Entity\Node;

/**
 * Service to format information from nodes to CSL displays.
 */
class CitationProcessorService implements CitationProcessorServiceInterface {

  /**
   * Plugin manager.
   *
   * @var Drupal\citation_select\CitationFieldFormatterInterface
   */
  protected $citationFieldFormatterManager;

  /**
   * Config factory service.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Date formatter service.
   *
   * @var Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Entity type manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($citationFieldFormatterManager, $configFactory, $dateFormatter, $entityTypeManager) {
    $this->citationFieldFormatterManager = $citationFieldFormatterManager;
    $this->configFactory = $configFactory;
    $this->dateFormatter = $dateFormatter;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Gets field map from settings.
   *
   * @return array
   *   Mapping of CSL-JSON fields to node fields for citations
   */
  protected function getFieldMap() {
    $config = $this->configFactory->get('citation_select.settings');
    return $config->get('csl_map');
  }

  /**
   * {@inheritdoc}
   *
   * @todo make default configurable
   */
  public function getCitationArray($nid, $langcode = 'en') {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    $data = [];

    // Get plugin definitions map.
    $plugin_map = [];
    $plugin_definitions = $this->citationFieldFormatterManager->getDefinitions();
    foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
      $field_type = $plugin_definition['field_type'];
      $plugin_map[$field_type] = $plugin_id;
    }
    // Get format array.
    foreach ($this->getFieldMap() as $node_field => $csl_fields) {
      // Mapping for formatter.
      $csl_map = [];
      foreach ($csl_fields as $csl_field) {
        $csl_map[$csl_field] = $this->getCslType($csl_field);
      }

      $field_type = $this->getFieldType($node, $node_field);
      if (isset($plugin_map[$field_type])) {
        $plugin = $this->citationFieldFormatterManager->createInstance($plugin_map[$field_type]);
      }
      else {
        $plugin = $this->citationFieldFormatterManager->createInstance('default');
      }
      $formatted = $plugin->formatMultiple($node, $node_field, $csl_map);
      if ($formatted != []) {
        $data = array_merge($data, $formatted);
      }
    }
    $type = $data['type'] ?? NULL;
    $data['type'] = $this->getValidType($type);
    return $data;
  }

  /**
   * Gets field type of a Drupal node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   JSON-CSL Node to get field type of.
   * @param string $node_field
   *   Name of node field to get type of.
   *
   * @return string
   *   Field type of $node_field from $node
   */
  protected function getFieldType(Node $node, $node_field) {
    $field_type = NULL;
    if ($node->hasField($node_field)) {
      $field_definition = $node->get($node_field)->getFieldDefinition();
      if ($field_definition != NULL) {
        $field_type = $field_definition->getType();
      }
    }
    return $field_type;

  }

  /**
   * Gets 'type' of CSL-JSON field (e.g. person, date, standard)
   *
   * @param string $csl_field
   *   JSON-CSL field to get type of.
   *
   * @return string
   *   'type' of CSL-JSON field (person, date, or standard)
   */
  protected function getCslType($csl_field) {
    $person_fields = [
      "author",
      "collection-editor",
      "composer",
      "container-author",
      "director",
      "editor",
      "editorial-director",
      "illustrator",
      "interviewer",
      "original-author",
      "recipient",
      "reviewed-author",
      "translator",
    ];
    $date_fields = [
      "accessed",
      "container",
      "event-date",
      "issued",
      "original-date",
      "submitted",
    ];

    if (in_array($csl_field, $person_fields)) {
      return "person";
    }
    elseif (in_array($csl_field, $date_fields)) {
      return "date";
    }
    else {
      return "standard";
    }
  }

  /**
   * Checks $type if it is valid.
   *
   * @param string $type
   *   Value to be checked for validity.
   *
   * @return bool
   *   Validitiy of $type.
   */
  protected function isValidType($type) {
    $types = [
      "article",
      "article-journal",
      "article-magazine",
      "article-newspaper",
      "bill",
      "book",
      "broadcast",
      "chapter",
      "classic",
      "collection",
      "dataset",
      "document",
      "entry",
      "entry-dictionary",
      "entry-encyclopedia",
      "event",
      "figure",
      "graphic",
      "hearing",
      "interview",
      "legal_case",
      "legislation",
      "manuscript",
      "map",
      "motion_picture",
      "musical_score",
      "pamphlet",
      "paper-conference",
      "patent",
      "performance",
      "periodical",
      "personal_communication",
      "post",
      "post-weblog",
      "regulation",
      "report",
      "review",
      "review-book",
      "software",
      "song",
      "speech",
      "standard",
      "thesis",
      "treaty",
      "webpage",
    ];
    return in_array($type, $types);
  }

  /**
   * Returns $type converted to a valid value of type.
   *
   * @param mixed $type
   *   Value to convert to a valid type.
   *
   * @return string
   *   Type of citation (e.g. book).
   */
  protected function getValidType($type) {
    $config = $this->configFactory->get('citation_select.settings');
    $field_map = $config->get('reference_type_field_map');

    if ($type != NULL) {
      $type = strtolower($type);
      // Try to do mapping from value.
      if ($field_map != NULL && array_key_exists($type, $field_map)) {
        return $field_map[$type];
      }
      // If there's no map, check if it's valid and return.
      elseif ($this->isValidType($type)) {
        return $type;
      }
    }
    // otherwise, set to 'document'.
    return 'document';
  }

  /**
   * Retrieves current time and converts it to CSL-JSON format.
   *
   * @return array
   *   current date converted to CSL-JSON format
   */
  protected function getNow() {
    $accessed = date_parse($this->dateFormatter->format(time(), 'short'));

    $data = [
      'date-parts' => [[
        $accessed['year'],
        $accessed['month'],
        $accessed['day'],
      ],
      ],
    ];

    return $data;
  }

}
