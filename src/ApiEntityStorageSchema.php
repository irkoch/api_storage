<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Schema\DynamicallyFieldableEntityStorageSchemaInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

class ApiEntityStorageSchema implements DynamicallyFieldableEntityStorageSchemaInterface {
  /**
   * {@inheritdoc}
   */
  public function finalizePurge(FieldStorageDefinitionInterface $storage_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionCreate(FieldStorageDefinitionInterface $storage_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionDelete(FieldStorageDefinitionInterface $storage_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionUpdate(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
  }

  /**
   * {@inheritdoc}
   */
  public function requiresEntityDataMigration(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresEntityStorageSchemaChanges(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresFieldDataMigration(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresFieldStorageSchemaChanges(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    return FALSE;
  }
}