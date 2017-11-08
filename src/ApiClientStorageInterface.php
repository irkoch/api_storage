<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\api_storage\Entity\ApiStorageEntityInterface;

interface ApiClientStorageInterface extends PluginInspectionInterface {
  /**
   * Return the name of the external entity storage client.
   *
   * @return string
   *   The name of the external entity storage client.
   */
  public function getName();

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return \Drupal\api_storage\ApiStorageEntityInterface|null
   *   An external entity object. NULL if no matching entity is found.
   */
  public function load($id);

  /**
   * Saves the entity permanently.
   *
   * @param \Drupal\api_storage\ApiStorageEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function save(ApiStorageEntityInterface $entity);

  /**
   * Deletes permanently saved entities.
   *
   * @param \Drupal\api_storage\ApiStorageEntityInterface $entity
   *   The external entity object to delete.
   */
  public function delete(ApiStorageEntityInterface $entity);

  /**
   * Query the external entities.
   *
   * @param array $parameters
   *   Key-value pairs of fields to query.
   */
  public function query(array $parameters);

  /**
   * Get HTTP headers to add.
   *
   * @return array
   *   Associative array of headers to add to the request.
   */
  public function getHttpHeaders();
}