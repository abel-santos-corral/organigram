<?php

namespace Drupal\organigram_node_types_kickstarter\Installer;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Installs starter Organigram node type configuration.
 */
class NodeTypesKickstarterInstaller implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The module machine name.
   */
  protected const MODULE_NAME = 'organigram_node_types_kickstarter';

  /**
   * The Organigram node type config entity type ID.
   */
  protected const NODE_TYPE_ENTITY = 'organigram_node_type';

  /**
   * Constructs a NodeTypesKickstarterInstaller object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleExtensionList $moduleList,
    protected Connection $database,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
      $container->get('database'),
      $container->get('messenger'),
    );
  }

  /**
   * Installs or updates starter node type config.
   */
  public function install(): void {
    $this->installNodeTypes();
    $this->migratePositionReferences();
    $this->deletePositionNodeType();
    $this->messenger->addStatus($this->t('Default organigram node types have been installed.'));
  }

  /**
   * Installs or updates node types from optional config.
   */
  protected function installNodeTypes(): void {
    $config_storage = new FileStorage($this->getConfigPath());
    $node_type_storage = $this->entityTypeManager
      ->getStorage(self::NODE_TYPE_ENTITY);

    foreach ($config_storage->listAll('organigram.organigram_node_type.') as $name) {
      $data = $config_storage->read($name);
      if (empty($data['id'])) {
        continue;
      }

      $node_type = $node_type_storage->load($data['id']);
      if ($node_type === NULL) {
        $node_type_storage->create($data)->save();
        continue;
      }

      $this->updateNodeType($node_type, $data);
    }
  }

  /**
   * Gets the optional config path.
   */
  protected function getConfigPath(): string {
    $module_path = $this->moduleList->getPath(self::MODULE_NAME);
    return DRUPAL_ROOT . '/' . $module_path . '/config/optional';
  }

  /**
   * Updates an existing Organigram node type.
   */
  protected function updateNodeType(object $node_type, array $data): void {
    foreach ($data as $key => $value) {
      if ($key !== 'id' && $key !== 'uuid') {
        $node_type->set($key, $value);
      }
    }
    $node_type->save();
  }

  /**
   * Reassigns existing node references from Position to Direction.
   */
  protected function migratePositionReferences(): void {
    $schema = $this->database->schema();
    $tables = [
      'node__field_organigram_node_type',
      'node_revision__field_organigram_node_type',
    ];

    foreach ($tables as $table) {
      if (!$schema->tableExists($table)) {
        continue;
      }
      $this->migratePositionReferencesInTable($table);
    }
  }

  /**
   * Reassigns Position references in a field data table.
   */
  protected function migratePositionReferencesInTable(string $table): void {
    $schema = $this->database->schema();
    foreach (['target_id', 'value'] as $suffix) {
      $column = 'field_organigram_node_type_' . $suffix;
      if (!$schema->fieldExists($table, $column)) {
        continue;
      }

      $this->database->update($table)
        ->fields([$column => 'direction'])
        ->condition($column, 'position')
        ->execute();
    }
  }

  /**
   * Deletes the old Position node type if present.
   */
  protected function deletePositionNodeType(): void {
    $node_type_storage = $this->entityTypeManager
      ->getStorage(self::NODE_TYPE_ENTITY);
    $position = $node_type_storage->load('position');
    if ($position !== NULL) {
      $position->delete();
    }
  }

}
