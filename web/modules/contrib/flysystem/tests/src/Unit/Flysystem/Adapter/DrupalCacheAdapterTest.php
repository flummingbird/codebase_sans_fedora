<?php

namespace Drupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\flysystem\Flysystem\Adapter\CacheItemBackend;
use Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter;
use Drupal\Tests\UnitTestCase;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

/**
 * Test the Drupal Cache Adapter.
 *
 * @group flysystem
 *
 * @coversDefaultClass \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter
 * @covers \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter
 */
class DrupalCacheAdapterTest extends UnitTestCase {

  /**
   * URI scheme to use for testing.
   *
   * @var string
   */
  const SCHEME = 'test-scheme';

  /**
   * The main test file.
   *
   * @var string
   */
  const FILE = 'test.txt';

  /**
   * The wrapped Flysytem adaper.
   *
   * @var \League\Flysystem\AdapterInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $adapter;

  /**
   * The cache adapter under test.
   *
   * @var \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter
   */
  protected $cacheAdapter;

  /**
   * The flysystem backend for testing.
   *
   * @var \Drupal\flysystem\Flysystem\Adapter\CacheItemBackend
   */
  protected $cacheItemBackend;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $time = $this->prophesize(TimeInterface::class);
    $this->cacheItemBackend = new CacheItemBackend(static::SCHEME, new MemoryBackend($time->reveal()));
    $this->adapter = $this->prophesize(AdapterInterface::class);
    $this->cacheAdapter = new DrupalCacheAdapter(static::SCHEME, $this->adapter->reveal(), $this->cacheItemBackend);
  }

  /**
   * Tests creating a public file.
   */
  public function testWriteSuccess() {
    $config = new Config();
    $this->adapter
      ->write(static::FILE, 'contents', $config)
      ->willReturn(['visibility' => 'public']);

    $metadata = $this->cacheAdapter->write(static::FILE, 'contents', $config);
    $this->assertSame('public', $metadata['visibility']);
    $this->assertSame('public', $this->cacheAdapter->getVisibility(static::FILE)['visibility']);
    $this->assertTrue($this->cacheItemBackend->has(static::FILE));
  }

  /**
   * Tests creating a public file stream.
   */
  public function testWriteStreamSuccess() {
    $config = new Config();
    $stream = fopen('data:text/plain,contents', 'rb');

    $this->adapter
      ->writeStream(static::FILE, $stream, $config)
      ->willReturn(['timestamp' => 12345]);

    $metadata = $this->cacheAdapter->writeStream(static::FILE, $stream, $config);
    $this->assertSame(12345, $metadata['timestamp']);
    $this->assertSame(12345, $this->cacheAdapter->getTimestamp(static::FILE)['timestamp']);
    $this->assertTrue($this->cacheItemBackend->has(static::FILE));
  }

  /**
   * Tests public file updates.
   */
  public function testUpdateSuccess() {
    $config = new Config();
    $this->adapter
      ->update(static::FILE, 'contents', $config)
      ->willReturn(['visibility' => 'public']);

    $metadata = $this->cacheAdapter->update(static::FILE, 'contents', $config);
    $this->assertSame('public', $metadata['visibility']);
    $this->assertSame('public', $this->cacheAdapter->getVisibility(static::FILE)['visibility']);
  }

  /**
   * Tests public file stream updates.
   */
  public function testUpdateStreamSuccess() {
    $config = new Config();
    $stream = fopen('data:text/plain,contents', 'rb');

    $this->adapter
      ->updateStream(static::FILE, $stream, $config)
      ->willReturn(['mimetype' => 'test_mimetype']);

    $metadata = $this->cacheAdapter->updateStream(static::FILE, $stream, $config);
    $this->assertSame('test_mimetype', $metadata['mimetype']);
    $this->assertSame('test_mimetype', $this->cacheAdapter->getMimetype(static::FILE)['mimetype']);
    $this->assertTrue($this->cacheItemBackend->has(static::FILE));
  }

  /**
   * Tests renaming a file.
   */
  public function testRenameSuccess() {
    $config = new Config();
    $this->adapter
      ->write(static::FILE, 'contents', $config)
      ->willReturn(['size' => 1234]);

    $this->cacheAdapter->write(static::FILE, 'contents', $config);

    $this->adapter
      ->rename(static::FILE, 'new.txt')
      ->willReturn(TRUE);

    $this->assertTrue($this->cacheAdapter->rename(static::FILE, 'new.txt'));
    $this->assertSame(1234, $this->cacheAdapter->getSize('new.txt')['size']);

    $this->assertFalse($this->cacheItemBackend->has(static::FILE));
    $this->assertTrue($this->cacheItemBackend->has('new.txt'));

  }

  /**
   * Tests copying a file.
   */
  public function testCopySuccess() {
    $config = new Config();
    $this->adapter
      ->write(static::FILE, 'contents', $config)
      ->willReturn(['size' => 1234]);

    $this->cacheAdapter->write(static::FILE, 'contents', $config);

    $this->adapter->copy(static::FILE, 'new.txt')->willReturn(TRUE);

    $this->assertTrue($this->cacheAdapter->copy(static::FILE, 'new.txt'));

    $this->assertSame(1234, $this->cacheAdapter->getSize(static::FILE)['size']);
    $this->assertSame(1234, $this->cacheAdapter->getSize('new.txt')['size']);
    $this->assertTrue($this->cacheItemBackend->has(static::FILE));
    $this->assertTrue($this->cacheItemBackend->has('new.txt'));
  }

  /**
   * Tests deleting a file.
   */
  public function testDeleteSuccess() {
    $config = new Config();
    $this->adapter
      ->write(static::FILE, 'contents', $config)
      ->willReturn(['size' => 1234]);

    $this->cacheAdapter->write(static::FILE, 'contents', $config);

    $this->adapter->delete(static::FILE)->willReturn(TRUE);

    $this->assertTrue($this->cacheAdapter->delete(static::FILE));
    $this->assertFalse($this->cacheItemBackend->has(static::FILE));
  }

  /**
   * Tests deleting a file directory.
   */
  public function testDeleteDirSuccess() {
    $config = new Config();
    // Create a directory with one sub file.
    $this->adapter->createDir('testdir', $config)->willReturn(['type' => 'dir']);
    $this->adapter->write('testdir/test.txt', 'contents', $config)
      ->willReturn(['size' => 1234]);
    $this->adapter->deleteDir('testdir')->willReturn(TRUE);
    $this->adapter->listContents('testdir', TRUE)->willReturn([
      ['path' => 'testdir'],
      ['path' => 'testdir/test.txt'],
    ]);

    $this->cacheAdapter->createDir('testdir', $config);
    $this->cacheAdapter->write('testdir/test.txt', 'contents', $config);

    $this->assertTrue($this->cacheAdapter->deleteDir('testdir'));

    $this->assertFalse($this->cacheItemBackend->has('testdir/test.txt'));
    $this->assertFalse($this->cacheItemBackend->has('testdir'));
  }

  /**
   * Tests visibility checking on private files.
   */
  public function testSetVisibilitySuccess() {
    $this->adapter
      ->setVisibility(static::FILE, 'private')
      ->willReturn(['visibility' => 'private']);

    $metadata = $this->cacheAdapter->setVisibility(static::FILE, 'private');
    $this->assertSame('private', $metadata['visibility']);
    $this->assertSame('private', $this->cacheAdapter->getVisibility(static::FILE)['visibility']);
    $this->assertTrue($this->cacheItemBackend->has(static::FILE));
  }

  /**
   * Tests file loading success.
   */
  public function testHasSuccess() {
    $cache_item = $this->cacheItemBackend->load(static::FILE);
    $this->cacheItemBackend->set(static::FILE, $cache_item);
    $this->assertTrue($this->cacheAdapter->has(static::FILE));
  }

  /**
   * Tests file loading failure.
   */
  public function testHasFail() {
    $this->adapter->has(static::FILE)->willReturn(TRUE);
    $this->assertTrue($this->cacheAdapter->has(static::FILE));
  }

  /**
   * Tests reading a file.
   */
  public function testRead() {
    $this->adapter->read(static::FILE)->willReturn([]);
    $this->assertIsArray($this->cacheAdapter->read(static::FILE));
  }

  /**
   * Tests reading a stream.
   */
  public function testReadStream() {
    $this->adapter->readStream(static::FILE)->willReturn([]);
    $this->assertIsArray($this->cacheAdapter->readStream(static::FILE));
  }

  /**
   * Tests listing contents of a directory.
   */
  public function testListContentsSuccess() {
    $this->adapter->listContents('testdir', TRUE)->willReturn([]);
    $this->assertIsArray($this->cacheAdapter->listContents('testdir', TRUE));
  }

  /**
   * Tests retrieving file metadata.
   */
  public function testGetMetadataSuccess() {
    $cache_item = $this->cacheItemBackend->load(static::FILE);
    $cache_item->updateMetadata(['type' => 'dir']);
    $this->cacheItemBackend->set(static::FILE, $cache_item);

    $this->assertSame('dir', $this->cacheAdapter->getMetadata(static::FILE)['type']);
  }

  /**
   * Tests failing to retrieve file metadata.
   */
  public function testGetMetadataFail() {
    $this->adapter->getMetadata(static::FILE)->willReturn(['type' => 'dir']);

    $this->assertSame('dir', $this->cacheAdapter->getMetadata(static::FILE)['type']);
  }

  /**
   * Tests failing to retrieve file size.
   */
  public function testGetSizeFail() {
    $this->adapter->getSize(static::FILE)->willReturn(['size' => 123]);

    $this->assertSame(123, $this->cacheAdapter->getSize(static::FILE)['size']);
  }

}
