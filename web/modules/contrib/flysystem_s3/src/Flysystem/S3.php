<?php

namespace Drupal\flysystem_s3\Flysystem;

use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flysystem\Flysystem\Adapter\MissingAdapter;
use Drupal\flysystem\Plugin\FlysystemPluginInterface;
use Drupal\flysystem\Plugin\FlysystemUrlTrait;
use Drupal\flysystem\Plugin\ImageStyleGenerationTrait;
use Drupal\flysystem_s3\AwsCacheAdapter;
use Drupal\flysystem_s3\Flysystem\Adapter\S3Adapter;
use League\Flysystem\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal plugin for the "S3" Flysystem adapter.
 *
 * @Adapter(id = "s3")
 */
class S3 implements FlysystemPluginInterface, ContainerFactoryPluginInterface {

  use ImageStyleGenerationTrait;
  use FlysystemUrlTrait {getExternalUrl as getDownloadUrl;
  }

  /**
   * The S3 bucket.
   *
   * @var string
   */
  protected $bucket;

  /**
   * The S3 client.
   *
   * @var \Aws\S3\S3Client
   */
  protected $client;

  /**
   * Options to pass into \League\Flysystem\AwsS3v3\AwsS3Adapter.
   *
   * @var array
   */
  protected $options;

  /**
   * The path prefix inside the bucket.
   *
   * @var string
   */
  protected $prefix;

  /**
   * The URL prefix.
   *
   * @var string
   */
  protected $urlPrefix;

  /**
   * Whether the stream is set to public.
   *
   * @var bool
   */
  protected $isPublic;

  /**
   * Logger channel.
   */
  protected LoggerInterface $loggerChannel;

  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * Constructs an S3 object.
   *
   * @param \Aws\S3\S3ClientInterface $client
   *   The S3 client.
   * @param \League\Flysystem\Config $config
   *   The configuration.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(S3ClientInterface $client, Config $config, LoggerChannelFactoryInterface $loggerChannelFactory, TimeInterface $time) {
    $this->client = $client;
    $this->bucket = $config->get('bucket', '');
    $this->prefix = $config->get('prefix', '');
    $this->isPublic = $config->get('public', FALSE);
    $this->options = $config->get('options', []);

    $this->urlPrefix = $this->calculateUrlPrefix($config);
    $this->loggerChannel = $loggerChannelFactory->get('flysystem_s3');
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = static::mergeConfiguration($container, $configuration);
    $client_config = static::mergeClientConfiguration($container, $configuration);

    $client = new S3Client($client_config);

    unset($configuration['key'], $configuration['secret']);

    return new self(
      $client,
      new Config($configuration),
      $container->get('logger.factory'),
      $container->get('datetime.time'));
  }

  /**
   * Returns an S3 client configuration based on a Flysystem configuration.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   *
   * @return array
   *   The client configuration.
   */
  public static function mergeClientConfiguration(ContainerInterface $container, array $configuration) {
    $client_config = [
      'version' => 'latest',
      'region' => $configuration['region'],
      'endpoint' => $configuration['endpoint'],
    ];
    // Add config for S3Client if the exists.
    if (isset($configuration['bucket_endpoint'])) {
      $client_config['bucket_endpoint'] = $configuration['bucket_endpoint'];
    }
    if (isset($configuration['use_accelerate_endpoint'])) {
      $client_config['use_accelerate_endpoint'] = $configuration['use_accelerate_endpoint'];
    }
    if (isset($configuration['use_dual_stack_endpoint'])) {
      $client_config['use_dual_stack_endpoint'] = $configuration['use_dual_stack_endpoint'];
    }
    if (isset($configuration['use_path_style_endpoint'])) {
      $client_config['use_path_style_endpoint'] = $configuration['use_path_style_endpoint'];
    }

    // Allow authentication with standard secret/key or IAM roles.
    if (isset($configuration['key']) && isset($configuration['secret'])) {
      $client_config['credentials'] = new Credentials($configuration['key'], $configuration['secret']);

      return $client_config;
    }

    $client_config['credentials.cache'] = new AwsCacheAdapter(
      $container->get('cache.default'),
      'flysystem_s3:'
    );

    return $client_config;
  }

  /**
   * Merges default Flysystem configuration.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   *
   * @return array
   *   The Flysystem configuration.
   */
  public static function mergeConfiguration(ContainerInterface $container, array $configuration) {
    $request = $container->get('request_stack')
      ->getCurrentRequest();

    if ($request && !isset($configuration['protocol'])) {
      $configuration['protocol'] = $request->getScheme();
    }

    return $configuration + [
      'region' => 'us-east-1',
      'endpoint' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    try {
      $adapter = new S3Adapter($this->client, $this->bucket, $this->time, $this->prefix, $this->options);
      return $adapter;
    }
    catch (S3Exception $e) {
      $message = $e->getMessage();
      $this->loggerChannel->error($message);
      return new MissingAdapter();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl($uri) {

    if ($this->isPublic === FALSE) {
      return $this->getDownloadUrl($uri);
    }

    $target = $this->getTarget($uri);

    if (strpos($target, 'styles/') === 0 && !file_exists($uri)) {
      $this->generateImageStyle($target);
    }

    return $this->urlPrefix . '/' . UrlHelper::encodePath($target);
  }

  /**
   * {@inheritdoc}
   */
  public function ensure($force = FALSE) {
    try {
      $exists = $this->client->doesBucketExistV2($this->bucket, FALSE);
    }
    catch (S3Exception | \Exception $e) {
      $message = $e->getMessage();
      $this->loggerChannel->error($message);
    }

    // @todo If the bucket exists, can we write to it? Find a way to test that.
    if (!$exists) {
      return [[
        'severity' => RfcLogLevel::ERROR,
        'message' => 'Bucket %bucket does not exist.',
        'context' => [
          '%bucket' => $this->bucket,
        ],
      ],
      ];
    }

    return [];
  }

  /**
   * Calculates the URL prefix.
   *
   * @param \League\Flysystem\Config $config
   *   The configuration.
   *
   * @return string
   *   The URL prefix in the form protocol://cname[/bucket][/prefix].
   */
  private function calculateUrlPrefix(Config $config) {
    $protocol = $config->get('protocol', 'http');

    $cname = (string) $config->get('cname');

    $prefix = (string) $config->get('prefix', '');
    $prefix = $prefix === '' ? '' : '/' . UrlHelper::encodePath($prefix);

    if ($cname !== '' && $config->get('cname_is_bucket', TRUE)) {
      return $protocol . '://' . $cname . $prefix;
    }

    $bucket = (string) $config->get('bucket', '');
    $bucket = $bucket === '' ? '' : '/' . UrlHelper::encodePath($bucket);

    // No custom CNAME was provided. Generate the default S3 one.
    if ($cname === '') {
      $cname = 's3-' . $config->get('region', 'us-east-1') . '.amazonaws.com';
    }

    // us-east-1 doesn't follow the consistent mapping.
    if ($cname === 's3-us-east-1.amazonaws.com') {
      $cname = 's3.amazonaws.com';
    }

    return $protocol . '://' . $cname . $bucket . $prefix;
  }

}
