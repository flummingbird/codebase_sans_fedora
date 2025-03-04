<?php

namespace Drupal\filehash\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\DeletedFieldsRepositoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\filehash\Batch\CleanBatch;
use Drupal\filehash\FileHashInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the file hash config form.
 */
class FileHashConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'filehash_config_form';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   Editable config names.
   */
  protected function getEditableConfigNames() {
    return ['filehash.settings'];
  }

  /**
   * {@inheritdoc}
   */
  final public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected DeletedFieldsRepositoryInterface $deletedFieldsRepository,
    protected FileHashInterface $fileHash,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_field.deleted_fields_repository'),
      $container->get('filehash'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   Renderable form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed[]
   *   Renderable form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['algorithms'] = [
      '#config_target' => new ConfigTarget(
        'filehash.settings',
        'algorithms',
        fn($value) => array_column(array_map(fn($key, $value) => [$key, $value ? $key : 0], array_keys($value), $value), 1, 0),
        [static::class, 'checkboxesToBooleans'],
      ),
      '#description' => $this->t('The checked hash algorithm(s) will be calculated when a file is uploaded. For optimum performance, only enable the hash algorithm(s) you need.'),
      '#options' => $this->fileHash->getAlgorithmNames(),
      '#title' => $this->t('Enabled hash algorithms'),
      '#type' => 'checkboxes',
    ];
    $form['autohash'] = [
      '#config_target' => 'filehash.settings:autohash',
      '#description' => $this->t('If enabled, missing hashes will be automatically generated and saved when loading a file. If disabled, missing hashes can only be <a href=":url">generated in bulk</a>.', [':url' => Url::fromRoute('filehash.generate')->toString()]),
      '#title' => $this->t('Automatically generate missing hashes when loading files'),
      '#type' => 'checkbox',
    ];
    $form['rehash'] = [
      '#config_target' => 'filehash.settings:rehash',
      '#description' => $this->t('If enabled, always regenerate the hash when saving a file, even if the hash has been generated previously. This should be enabled if you have modules that modify existing files or apply processing to uploaded files (e.g. core Image module with maximum image resolution set), and you want to keep the hash in sync with the file on disk. If disabled, the file hash represents the hash of the originally uploaded file, and will only be generated if it is missing, which is much faster.'),
      '#title' => $this->t('Always rehash file when saving'),
      '#type' => 'checkbox',
    ];
    $form['original'] = [
      '#config_target' => 'filehash.settings:original',
      '#description' => $this->t('If enabled, store an additional "original" hash for each uploaded file which will not be updated. This is only useful if the above "always rehash" setting is also enabled (otherwise the file hash itself represents the hash of the originally uploaded file).'),
      '#title' => $this->t('Store an additional original hash for each uploaded file'),
      '#type' => 'checkbox',
    ];
    $form['mime_types'] = [
      '#config_target' => new ConfigTarget(
        'filehash.settings',
        'mime_types',
        fn($value) => implode(PHP_EOL, $value ?? []),
        fn($value) => preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY),
      ),
      '#description' => $this->t('If set, only these MIME types will be hashed. If empty, all files will be hashed. MIME types (e.g. <em>application/octet-stream</em>) can be separated by newline, space, tab or comma.'),
      '#title' => $this->t('List of MIME types to hash'),
      '#type' => 'textarea',
    ];
    $form['suppress_warnings'] = [
      '#config_target' => 'filehash.settings:suppress_warnings',
      '#description' => $this->t('If enabled, do not log a warning when attempting to hash a nonexistent or unreadable file.'),
      '#title' => $this->t('Suppress warnings'),
      '#type' => 'checkbox',
    ];
    $form['dedupe'] = [
      '#config_target' => 'filehash.settings:dedupe',
      '#description' => $this->t('If enabled, prevent duplicate uploaded files from being saved when the file already exists as a permanent file. If strict, also include temporary files in the duplicate check, which prevents duplicates from being uploaded at the same time. If off, you can still disallow duplicate files in the widget settings for any particular file upload field. Note, enabling this setting has privacy implications, as it allows users to determine if a particular file has been uploaded to the site.'),
      '#title' => $this->t('Disallow duplicate files'),
      '#options' => [
        $this->t('Off (use field widget settings)'),
        $this->t('Enabled'),
        $this->t('Strict'),
      ],
      '#type' => 'radios',
    ];
    $form['dedupe_original'] = [
      '#config_target' => 'filehash.settings:dedupe_original',
      '#description' => $this->t('If enabled, also prevent an uploaded file from being saved if its hash matches the "original" hash of another file. This is useful if you apply processing to uploaded files (e.g. core Image module with maximum image resolution set), and want to check uploads against both the original and derivative file hash. Only active if the above original file hash and dedupe settings are enabled.'),
      '#title' => $this->t('Include original file hashes in duplicate check'),
      '#type' => 'checkbox',
    ];
    $form['#attached']['library'][] = 'filehash/admin';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   Renderable form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $algorithms = $form_state->getValue('algorithms');
    assert(is_array($algorithms));
    foreach ($algorithms as $algorithm => $value) {
      if ($value) {
        if ($this->deletedFieldsRepository->getFieldDefinitions("file-$algorithm")) {
          $form_state->setErrorByName("algorithms][$algorithm", $this->t('Please run cron first to finish deleting the %label column before enabling it.', [
            '%label' => $this->fileHash::getAlgorithmLabel($algorithm),
          ]));
        }
        if ($form_state->getValue('original') && $this->deletedFieldsRepository->getFieldDefinitions("file-original_$algorithm")) {
          $form_state->setErrorByName('original', $this->t('Please run cron first to finish deleting the %label column before enabling it.', [
            '%label' => $this->fileHash::getAlgorithmLabel($algorithm, TRUE),
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   Renderable form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    if (CleanBatch::columns()) {
      $this->messenger()->addStatus($this->t('Please visit the <a href="@url">clean-up tab</a> to remove unused database columns.', [
        '@url' => Url::fromRoute('filehash.clean')->toString(),
      ]));
    }
  }

  /**
   * Extracts boolean configs from checkbox values.
   *
   * @param array<string, string|int> $checkboxes
   *   Checkbox values.
   *
   * @return array<string, bool>
   *   Boolean configuration list.
   */
  public static function checkboxesToBooleans(array $checkboxes): array {
    return array_map(fn($value) => (bool) $value, $checkboxes);
  }

}
