<?php

namespace Drupal\field_report\Controller;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field Report Controller.
 *
 * @package Drupal\field_report\Controller
 */
class FieldReportController extends ControllerBase {

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(EntityFieldManager $entityFieldManager, EntityTypeManager $entityTypeManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Service injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container object.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Return Entities listing and fields.
   *
   * @return array
   *   Returns an array of bundles and theirs fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getEntityBundles() {
    $entityList = $this->entityTypeManager->getDefinitions();
    $allFields = $this->entityFieldManager->getFieldMap();

    $fieldListings = [];
    foreach ($entityList as $entityKey => $entityValue) {

      // If the Entity has bundle_entity_type set we grab it.
      $bundle_entity_type = $entityValue->get('bundle_entity_type');

      // Check to see if the entity has any bundle before continuing.
      if (!empty($bundle_entity_type)) {
        $entityTypes = $this->entityTypeManager->getStorage($bundle_entity_type)
          ->loadMultiple();

        // Override the Entity Title / Label for select entities.
        switch ($entityKey) {
          case 'block_content':
            $bundleParent = $this->t('Blocks');
            break;

          case 'comment':
            $bundleParent = $this->t('Comments');
            break;

          case 'contact_message':
            $bundleParent = $this->t('Contact Forms');
            break;

          case 'media':
            $bundleParent = $this->t('Media');
            break;

          case 'node':
            $bundleParent = $this->t('Content Types');
            break;

          case 'shortcut':
            $bundleParent = $this->t('Shortcut Menus');
            break;

          case 'taxonomy_term':
            $bundleParent = $this->t('Taxonomy Terms');
            break;

          default:
            $entityLabel = $entityValue->get('label');
            $labelArray = (array) $entityLabel;
            $bundleParent = reset($labelArray);
        }

        // Output the Parent Entity label.
        $fieldListings[] = [
          '#type' => 'markup',
          '#markup' => "<h1 class='fieldReportTable--h1'>" . $bundleParent . "</h1><hr />",
        ];

        foreach ($entityTypes as $entityType) {
          // Load in the entityType fields.
          $fields = $this->entityTypeFields($entityKey, $entityType->id());

          foreach ($fields as $field => $field_array) {
            $relatedBundles = [];
            $entityOptions = [];

            // Get the related / used in bundles from the field.
            $relatedBundlesArray = $allFields[$entityKey][$field]['bundles'];

            // Create the edit field URLs.
            if ($field_array->access('update') && $field_array->hasLinkTemplate("{$field_array->getTargetEntityTypeId()}-field-edit-form")) {
              $editRoute = $field_array->toUrl("{$field_array->getTargetEntityTypeId()}-field-edit-form");
              $entityEdit = Link::fromTextAndUrl('Edit', $editRoute);
              $entityOptions[] = $entityEdit;
            }

            if ($field_array->access('delete') && $field_array->hasLinkTemplate("{$field_array->getTargetEntityTypeId()}-field-delete-form")) {
              // Create the delete field URLs.
              $deleteRoute = $field_array->toUrl("{$field_array->getTargetEntityTypeId()}-field-delete-form");
              $entityDelete = Link::fromTextAndUrl('Delete', $deleteRoute);
              $entityOptions[] = $entityDelete;
            }

            // Loop through related bundles.
            foreach ($relatedBundlesArray as $relatedBundlesValue) {
              if ($entityTypes[$relatedBundlesValue]->id() != $entityType->id()) {
                $relatedBundlesURL = $entityTypes[$relatedBundlesValue]->toUrl('edit-form');
                $relatedBundlesLabel = $entityTypes[$relatedBundlesValue]->label();
                if ($relatedBundlesURL) {
                  $relatedBundles[] = Link::fromTextAndUrl($relatedBundlesLabel, $relatedBundlesURL);
                }
                else {
                  $relatedBundles[] = $relatedBundlesLabel;
                }
              }
            }

            $relatedBundlesRow['data']['related']['data'] = [
              '#theme' => 'item_list',
              '#items' => $relatedBundles,
              '#context' => ['list_style' => 'comma-list'],
            ];

            $entityOptionsEditDelete['data']['options']['data'] = [
              '#theme' => 'item_list',
              '#items' => $entityOptions,
              '#context' => ['list_style' => 'comma-list'],
            ];

            // Build out our table for the fields.
            $rows[] = [
              $field_array->get('label'),
              $field_array->get('field_type'),
              $field_array->get('description'),
              $relatedBundlesRow,
              $entityOptionsEditDelete,
            ];
          }

          // Output the field label.
          $fieldListings[] = [
            '#type' => 'markup',
            '#markup' => "<h3 class='fieldReportTable--h3'>" . $entityType->label() . "</h3>",
          ];

          // Output the field description.
          $fieldListings[] = [
            '#type' => 'markup',
            '#markup' => "<p>" . $entityType->get('description') . "</p>",
          ];

          // If no rows exist we display a no results message.
          if (!empty($rows)) {
            $fieldListings[] = [
              '#type' => 'table',
              '#header' => [
                $this->t('Field Label'),
                $this->t('Field Type'),
                $this->t('Field Description'),
                $this->t('Also Used In'),
                $this->t('Options'),
              ],
              '#rows' => $rows,
              '#attributes' => [
                'class' => ['fieldReportTable'],
              ],
              '#attached' => [
                'library' => [
                  'field_report/field-report',
                ],
              ],
            ];
          }
          else {
            $fieldListings[] = [
              '#type' => 'markup',
              '#markup' => $this->t("<p><b>No Fields are avaliable.</b></p>"),
            ];
          }

          // Clear out the rows array to start fresh.
          unset($rows);
        }
      }
    }

    return $fieldListings;
  }

  /**
   * Helper function to get the field definitions.
   *
   * @param string $entityKey
   *   The entity's name.
   * @param string $contentType
   *   The content type name.
   *
   * @return array
   *   Returns an array of the fields.
   */
  public function entityTypeFields($entityKey, $contentType) {
    $fields = [];

    if (!empty($entityKey) && !empty($contentType)) {
      $fields = array_filter(
        $this->entityFieldManager->getFieldDefinitions($entityKey, $contentType), function ($field_definition) {
          return $field_definition instanceof FieldConfigInterface;
        }
      );
    }

    return $fields;
  }

}
