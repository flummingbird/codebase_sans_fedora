<?php

namespace Drupal\Tests\rdf\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rdf\Entity\RdfMapping;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\rdf\Entity\RdfMapping
 * @group rdf
 */
class RdfMappingConfigEntityUnitTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The UUID generator used for testing.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeId = $this->randomMachineName();

    $this->entityType = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->entityType->expects($this->any())
      ->method('getProvider')
      ->willReturn('entity');

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $this->uuid = $this->createMock('\Drupal\Component\Uuid\UuidInterface');

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);
    \Drupal::setContainer($container);

  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependencies(): void {
    $target_entity_type_id = $this->randomMachineName(16);

    $target_entity_type = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $target_entity_type->expects($this->any())
      ->method('getProvider')
      ->willReturn('test_module');
    $values = ['targetEntityType' => $target_entity_type_id];
    $target_entity_type->expects($this->any())
      ->method('getBundleEntityType')
      ->willReturn(NULL);
    $target_entity_type->expects($this->any())
      ->method('getBundleConfigDependency')
      ->willReturn(['type' => 'module', 'name' => 'test_module']);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->willReturnMap([
        [$target_entity_type_id, TRUE, $target_entity_type],
        [$this->entityTypeId, TRUE, $this->entityType],
      ]);

    $entity = new RdfMapping($values, $this->entityTypeId);
    $dependencies = $entity->calculateDependencies()->getDependencies();
    $this->assertArrayNotHasKey('config', $dependencies);
    $this->assertContains('test_module', $dependencies['module']);
  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependenciesWithEntityBundle(): void {
    $target_entity_type_id = $this->randomMachineName(16);
    $target_entity_type = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $target_entity_type->expects($this->any())
      ->method('getProvider')
      ->willReturn('test_module');
    $bundle_id = $this->randomMachineName(10);
    $values = ['targetEntityType' => $target_entity_type_id , 'bundle' => $bundle_id];

    $target_entity_type->expects($this->any())
      ->method('getBundleConfigDependency')
      ->willReturn(['type' => 'config', 'name' => 'test_module.type.' . $bundle_id]);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->willReturnMap([
        [$target_entity_type_id, TRUE, $target_entity_type],
        [$this->entityTypeId, TRUE, $this->entityType],
      ]);

    $entity = new RdfMapping($values, $this->entityTypeId);
    $dependencies = $entity->calculateDependencies()->getDependencies();
    $this->assertContains('test_module.type.' . $bundle_id, $dependencies['config']);
    $this->assertContains('test_module', $dependencies['module']);
  }

}
