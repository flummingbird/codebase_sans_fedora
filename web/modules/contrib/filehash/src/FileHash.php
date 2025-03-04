<?php

namespace Drupal\filehash;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;

/**
 * Provides the File Hash service.
 */
class FileHash implements FileHashInterface {

  use StringTranslationTrait;

  const CHUNK_SIZE = 8192;

  /**
   * Constructs the File Hash service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    protected MemoryCacheInterface $memoryCache,
    protected ?EntityTypeManagerInterface $entityTypeManager = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function addColumns(): void {
    $original = $this->configFactory->get('filehash.settings')->get('original');
    $fields = $this->entityBaseFieldInfo();
    foreach ($this->getEnabledAlgorithms() as $algorithm) {
      $this->entityDefinitionUpdateManager->installFieldStorageDefinition($algorithm, 'file', 'file', $fields[$algorithm]);
      if ($original) {
        $this->entityDefinitionUpdateManager->installFieldStorageDefinition("original_$algorithm", 'file', 'file', $fields["original_$algorithm"]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateLookup(string $column, FileInterface $file, bool $strict = FALSE, bool $original = FALSE): ?string {
    if (is_null($file->{$column}->value)) {
      return NULL;
    }
    // For BC with cached containers, entity type manager is nullable.
    assert(isset($this->entityTypeManager));
    // @fixme This code results in *multiple* SQL joins on the file_managed
    // table; if slow maybe it should be refactored to use a normal database
    // query? See also https://www.drupal.org/project/drupal/issues/2875033
    $query = $this->entityTypeManager->getStorage('file')->getQuery();
    if ($original && $this->configFactory->get('filehash.settings')->get('original')) {
      $group = $query->orConditionGroup()
        ->condition("original_$column", $file->{$column}->value)
        ->condition($column, $file->{$column}->value);
      $query->condition($group);
    }
    else {
      $query->condition($column, $file->{$column}->value);
    }
    if (!$strict) {
      $query->condition('status', FileInterface::STATUS_PERMANENT);
    }
    $results = $query->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    return reset($results) ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function entityBaseFieldInfo(): array {
    $algorithms = $this->getEnabledAlgorithms();
    $original = $this->configFactory->get('filehash.settings')->get('original');
    $fields = [];
    foreach ($algorithms as $algorithm) {
      $name = $this->getAlgorithmName($algorithm);
      $fields[$algorithm] = BaseFieldDefinition::create('filehash')
        ->setLabel(static::getAlgorithmLabel($algorithm))
        ->setSetting('max_length', $this->getAlgorithmLength($algorithm))
        ->setDescription($this->t('The @algo hash for this file.', ['@algo' => $name]));
      if ($original) {
        $fields["original_$algorithm"] = BaseFieldDefinition::create('filehash')
          ->setLabel(static::getAlgorithmLabel($algorithm, TRUE))
          ->setSetting('max_length', $this->getAlgorithmLength($algorithm))
          ->setDescription($this->t('The original @algo hash for this file.', ['@algo' => $name]));
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function entityStorageLoad(array $files): void {
    if (!$this->configFactory->get('filehash.settings')->get('autohash')) {
      return;
    }
    foreach ($files as $file) {
      foreach ($this->getEnabledAlgorithms() as $algorithm) {
        if (!$file->{$algorithm}->value && $this->shouldHash($file) && !$this->memoryCache->get((string) $file->id())) {
          // To avoid endless loops, auto-hash each file once per execution.
          $this->memoryCache->set((string) $file->id(), TRUE);
          $file->save();
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filePresave(FileInterface $file): void {
    if ($this->configFactory->get('filehash.settings')->get('rehash')) {
      // Regenerate all hashes.
      $this->hash($file);
    }
    else {
      // Only generate missing hashes.
      foreach ($this->getEnabledAlgorithms() as $algorithm) {
        if (empty($file->{$algorithm}->value)) {
          $algorithms[] = $algorithm;
        }
      }
      if (isset($algorithms)) {
        $this->hash($file, $algorithms);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledAlgorithms(): array {
    $config = $this->configFactory->get('filehash.settings')->get('algorithms');
    if (!is_array($config)) {
      return [];
    }
    return array_intersect_key(static::getAlgorithms(), array_filter($config));
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledAlgorithmNames(): array {
    return array_map(function (string $algorithm): TranslatableMarkup {
      return static::getAlgorithm($algorithm)->getName();
    }, $this->getEnabledAlgorithms());
  }

  /**
   * {@inheritdoc}
   */
  public function hash(FileInterface $file, ?array $algorithms = NULL, bool $original = FALSE): void {
    // If columns are set, only generate those hashes.
    $algorithms = isset($algorithms) ? array_intersect($this->getEnabledAlgorithms(), $algorithms) : $this->getEnabledAlgorithms();
    if (!$algorithms) {
      return;
    }
    $setFileHashes = function (array $states = []) use ($file, $algorithms, $original): void {
      foreach ($algorithms as $algorithm) {
        // Unreadable files will have NULL hash values.
        $hash = $states[$algorithm] ?? NULL;
        $file->set($algorithm, $hash);
        if ($original) {
          $file->set("original_$algorithm", $hash);
        }
      }
    };
    if (!$this->shouldHash($file)) {
      $setFileHashes();
      return;
    }
    $uri = $file->getFileUri();
    if (NULL === $uri) {
      $setFileHashes();
      return;
    }
    $suppressWarnings = $this->configFactory->get('filehash.settings')->get('suppress_warnings');
    // Use hash_file() if possible as it provides an optimized code path.
    if (count($algorithms) === 1 && static::getAlgorithm($algorithm = reset($algorithms))->getMechanism() === Mechanism::Hash) {
      $algo = static::getAlgorithm($algorithm)->getHashAlgo();
      assert(is_string($algo));
      $states[$algorithm] = ($suppressWarnings ? @hash_file($algo, $uri) : hash_file($algo, $uri)) ?: NULL;
      $setFileHashes($states);
      return;
    }
    $handle = $suppressWarnings ? @fopen($uri, 'rb') : fopen($uri, 'rb');
    if (FALSE === $handle) {
      $setFileHashes();
      return;
    }
    foreach ($algorithms as $algorithm) {
      if ($state = static::getAlgorithm($algorithm)->getStateMachine()) {
        $states[$algorithm] = $state;
      }
    }
    if (empty($states)) {
      $setFileHashes();
      return;
    }
    while ('' !== ($data = fread($handle, static::CHUNK_SIZE))) {
      if (FALSE === $data) {
        $setFileHashes();
        return;
      }
      foreach ($states as $state) {
        $state->update($data);
      }
    }
    if (!feof($handle)) {
      $setFileHashes();
      return;
    }
    fclose($handle);
    foreach ($states as &$state) {
      $state = $state->final();
    }
    $setFileHashes($states);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldHash(FileInterface $file): bool {
    // Nothing to do if file URI is empty.
    if (!$file->getFileUri()) {
      return FALSE;
    }
    $types = $this->configFactory->get('filehash.settings')->get('mime_types') ?? [];
    assert(is_array($types));
    if ($types && !in_array($file->getMimeType(), $types)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithm(string $algorithm): AlgorithmInterface {
    return Algorithm::from($algorithm);
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithmLabel(string $algorithm, bool $original = FALSE): TranslatableMarkup {
    $name = static::getAlgorithmName($algorithm);
    return $original ? t('Original @algo hash', ['@algo' => $name]) : t('@algo hash', ['@algo' => $name]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithmLength(string $algorithm): int {
    return static::getAlgorithm($algorithm)->getHexadecimalLength();
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithmName(string $algorithm): TranslatableMarkup {
    return static::getAlgorithm($algorithm)->getName();
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithmNames(): array {
    $names = [];
    foreach (static::getAlgorithms() as $algorithm) {
      $names[$algorithm] = static::getAlgorithm($algorithm)->getName();
    }
    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithms(): array {
    $algorithms = array_column(Algorithm::cases(), 'value');
    return array_combine($algorithms, $algorithms);
  }

}
