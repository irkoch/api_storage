<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Class ApiClientStorage
 *
 * @see \Drupal\api_storage\ApiClientStorageManager
 * @see plugin_api
 *
 * @Annotation
 */
class ApiClientStorage extends Plugin {
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the storage client.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The resource url
   *
   * @var string
   */
  public $resources_url;
}

