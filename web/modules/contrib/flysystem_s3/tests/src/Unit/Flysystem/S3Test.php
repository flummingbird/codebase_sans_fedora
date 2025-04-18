<?php

namespace Drupal\Tests\flysystem_s3\Unit\Flysystem;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Drupal\Component\Datetime\Time;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\flysystem_s3\Flysystem\S3;
use Drupal\Tests\UnitTestCase;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// cspell:ignore fsdf sfsdf

/**
 * @coversDefaultClass \Drupal\flysystem_s3\Flysystem\S3
 * @covers \Drupal\flysystem_s3\Flysystem\S3
 * @group flysystem_s3
 */
class S3Test extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Mock of time service.
   */
  protected TimeInterface $time;

  /**
   * Mock of logger channel factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Creates reusable mocks.
   */
  public function __construct() {
    parent::__construct();
    $this->time = $this->prophesize(Time::class)->reveal();
    $loggerChannel = $this->prophesize(LoggerChannel::class)->reveal();
    $loggerFactoryProphecy = $this->prophesize(LoggerChannelFactory::class);
    $loggerFactoryProphecy->get('flysystem_s3')->willReturn($loggerChannel);
    $this->loggerFactory = $loggerFactoryProphecy->reveal();
  }

  /**
   * @covers ::__construct
   * @covers ::getExternalUrl
   */
  public function testGetExternalUrl() {
    $configuration = new Config([
      'bucket' => 'example-bucket',
      'cname' => 'example.com',
      'prefix' => 'test prefix',
      'public' => TRUE,
    ]);

    $client = new S3Client([
      'version' => 'latest',
      'region' => 'beep',
      'credentials' => new Credentials('foo', 'bar'),
    ]);

    $plugin = new S3($client, $configuration, $this->loggerFactory, $this->time);

    $this->assertInstanceOf(AdapterInterface::class, $plugin->getAdapter());
    $this->assertSame('http://example.com/test%20prefix/foo%201.html', $plugin->getExternalUrl('s3://foo 1.html'));

    $configuration->set('prefix', '');

    $plugin = new S3($client, $configuration, $this->loggerFactory, $this->time);
    $this->assertSame('http://example.com/foo%201.html', $plugin->getExternalUrl('s3://foo 1.html'));
  }

  /**
   * @covers ::mergeConfiguration
   * @covers ::mergeClientConfiguration
   */
  public function testMergeConfiguration() {
    $container = new ContainerBuilder();
    $container->set('request_stack', new RequestStack());
    $container->get('request_stack')->push(Request::create('https://example.com/'));

    $configuration = [
      'key'    => 'fee',
      'secret' => 'fo',
      'region' => 'eu-west-1',
      'bucket' => 'example-bucket',
    ];

    $configuration = S3::mergeConfiguration($container, $configuration);
    $this->assertSame('https', $configuration['protocol']);

    $client_config = S3::mergeClientConfiguration($container, $configuration);
    $this->assertSame('eu-west-1', $client_config['region']);
    $this->assertNull($client_config['endpoint']);
    $this->assertInstanceOf(Credentials::class, $client_config['credentials']);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = new ContainerBuilder();
    $container->set('request_stack', new RequestStack());
    $container->get('request_stack')->push(Request::create('https://example.com/'));
    $container->get('request_stack')->push(Request::create('https://example.com/'));
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('datetime.time', $this->time);

    $configuration = [
      'key'    => 'fee',
      'secret' => 'fo',
      'region' => 'eu-west-1',
      'bucket' => 'example-bucket',
    ];

    $plugin = S3::create($container, $configuration, '', '');
    $this->assertInstanceOf(S3::class, $plugin);
  }

  /**
   * @covers ::create
   * @covers ::getAdapter
   */
  public function testCreateUsingNonAwsConfiguration() {
    $container = new ContainerBuilder();
    $container->set('request_stack', new RequestStack());
    $container->get('request_stack')->push(Request::create('https://example.com/'));
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('datetime.time', $this->time);

    $configuration = [
      'key'      => 'fee',
      'secret'   => 'fo',
      'region'   => 'eu-west-1',
      'cname'    => 'something.somewhere.tld',
      'endpoint' => 'https://api.somewhere.tld',
      'public'   => TRUE,
    ];

    $plugin = S3::create($container, $configuration, '', '');
    $this->assertSame('https://something.somewhere.tld/foo%201.html', $plugin->getExternalUrl('s3://foo 1.html'));
    $this->assertSame('https://api.somewhere.tld', (string) $plugin->getAdapter()->getClient()->getEndpoint());
  }

  /**
   * @covers ::create
   * @covers ::getExternalUrl
   * @covers ::getAdapter
   */
  public function testCreateUsingNonAwsConfigurationWithBucket() {
    $container = new ContainerBuilder();
    $container->set('request_stack', new RequestStack());
    $container->get('request_stack')->push(Request::create('http://example.com/'));
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('datetime.time', $this->time);

    $configuration = [
      'key'             => 'foo',
      'secret'          => 'bar',
      'cname'           => 'storage.example.com',
      'cname_is_bucket' => FALSE,
      'bucket'          => 'my-bucket',
      'endpoint'        => 'https://api.somewhere.tld',
      'public'          => TRUE,
    ];

    $plugin = S3::create($container, $configuration, '', '');
    $this->assertSame('http://storage.example.com/my-bucket/foo%201.html', $plugin->getExternalUrl('s3://foo 1.html'));
    $this->assertSame('https://api.somewhere.tld', (string) $plugin->getAdapter()->getClient()->getEndpoint());
  }

  /**
   * @covers ::__construct
   * @covers ::getExternalUrl
   */
  public function testEmptyCnameDoesNotBreakConfiguration() {
    $configuration = new Config([
      'cname'    => NULL,
      'bucket'   => 'my-bucket',
      'public'   => TRUE,
    ]);

    $client = new S3Client([
      'version' => 'latest',
      'region' => 'beep',
      'credentials' => new Credentials('fsdf', 'sfsdf'),
    ]);

    $plugin = new S3($client, $configuration, $this->loggerFactory, $this->time);
    $this->assertSame('http://s3.amazonaws.com/my-bucket/foo.html', $plugin->getExternalUrl('s3://foo.html'));
  }

  /**
   * @covers ::ensure
   * @throws \Exception
   */
  public function testEnsure() {
    $configuration = new Config([
      'cname'    => NULL,
      'bucket'   => 'my-bucket',
      'public'   => TRUE,
    ]);

    $client = $this->prophesize(S3ClientInterface::class);
    $client->willImplement(S3ClientInterface::class);
    $client->doesBucketExistV2(Argument::type('string'), FALSE)->willReturn(TRUE);
    $client->getPaginator('ListObjects', Argument::type('array'))
      ->willReturn([]);
    $plugin = new S3($client->reveal(), $configuration, $this->loggerFactory, $this->time);

    $this->assertSame([], $plugin->ensure());

    $client->doesBucketExistV2(Argument::type('string'), FALSE)->willReturn(FALSE);
    $plugin = new S3($client->reveal(), new Config(['bucket' => 'example-bucket']), $this->loggerFactory, $this->time);

    $result = $plugin->ensure();
    $this->assertCount(1, $result);
    $this->assertSame(RfcLogLevel::ERROR, $result[0]['severity']);
  }

}
