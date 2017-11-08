<?php
/**
 * @filefff
 */

namespace Drupal\api_storage\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * Provide an interface defining a api storage entity.
 */
interface ApiStorageEntityInterface extends ContentEntityInterface {
  /**
   * Get the fidelio entity type.
   *
   * @return StringArgument
   *  The fidelio entity type.
   */
  public function getType();

  /**
   * @return int|null
   *  The fidelio entity identifier, or null if the object does not yet have an id.
   */
  public function fid();

  /**
   * Map this entity to a \stdclass object.
   *
   * @return \stdClass
   *  The mapped object.
   */
  public function getMappedObject();

  /**
   * Map a \stdClass to this entity.
   *
   * @param \stdClass $object
   *
   * @return $this
   */
  public function mapObject($object);
}