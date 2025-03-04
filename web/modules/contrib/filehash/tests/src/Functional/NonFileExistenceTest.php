<?php

namespace Drupal\Tests\filehash\Functional;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\Tests\file\Functional\FileFieldTestBase;

/**
 * Tests entity CRUD interactions when a file does not exist.
 *
 * @group filehash
 */
class NonFileExistenceTest extends FileFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filehash',
    'file',
    'filehash_entity_crud_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/media/filehash');
    $fields = ['algorithms[sha1]' => TRUE];
    $this->submitForm($fields, 'Save configuration');
  }

  /**
   * Tests that a non-existent URI doesn't cause an infinite loop when loaded.
   *
   * @large
   */
  public function testCrudHooksLooping(): void {
    $file = @File::create([
      'uid' => 1,
      'uri' => "temporary://{$this->randomMachineName()}",
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    @$file->save();
    $this->assertSame('1', $file->id());
  }

}
