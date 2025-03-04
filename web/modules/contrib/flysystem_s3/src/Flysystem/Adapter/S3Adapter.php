<?php

namespace Drupal\flysystem_s3\Flysystem\Adapter;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface;
use Drupal\Component\Datetime\TimeInterface;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Config;
use League\Flysystem\Util;
use League\Flysystem\Util\MimeType;

/**
 * Overrides methods so it works with Drupal.
 */
class S3Adapter extends AwsS3Adapter {
  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(S3ClientInterface $client, $bucket, TimeInterface $time, $prefix = '', array $options = [], $streamReads = TRUE) {
    // In order to stat files by specifying non-streaming http option which has
    // become default setting as of league/flysystem-aws-s3-v3 version 1.0.25.
    // @see https://www.drupal.org/project/flysystem_s3/issues/3172969
    if (!isset($options['@http']['stream'])) {
      $options['@http']['stream'] = FALSE;
    }

    parent::__construct($client, $bucket, $prefix, $options, $streamReads);
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function has($path) {
    $location = $this->applyPathPrefix($path);
    try {
      if ($this->s3Client->doesObjectExistV2($this->bucket, $location, FALSE, $this->options)) {
        return TRUE;
      }
      if ($this->s3Client->doesObjectExistV2($this->bucket, $location . '/', FALSE) === TRUE) {
        return TRUE;
      }
      else {
        return $this->doesDirectoryExist($location);
      }
    }
    catch (S3Exception | \Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($path) {
    $metadata = parent::getMetadata($path);

    if ($metadata === FALSE) {
      return [
        'type' => 'dir',
        'path' => $path,
        'timestamp' => $this->time->getRequestTime(),
        'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
      ];
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  protected function upload($path, $body, Config $config) {
    $key = $this->applyPathPrefix($path);
    $options = $this->getOptionsFromConfig($config);
    $acl = $options['ACL'] ?? 'private';

    if (!isset($options['ContentType'])) {
      if (is_string($body)) {
        $options['ContentType'] = Util::guessMimeType($path, $body);
      }
      else {
        $options['ContentType'] = MimeType::detectByFilename($path);
      }
    }

    if (!isset($options['ContentLength'])) {
      $options['ContentLength'] = is_string($body) ? Util::contentSize($body) : Util::getStreamSize($body);
    }

    $this->s3Client->upload($this->bucket, $key, $body, $acl, ['params' => $options]);

    return $this->normalizeResponse($options, $key);
  }

}
