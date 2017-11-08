<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Define a ApiEndpoint annotation object.
 *
 * @ingroup api_storage
 *
 * @Annotation
 */
class ApiEndpoint extends Plugin {
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The administrative label.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label = '';

  /**
   * The endpoint resources type description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

  /**
   * The endpoint uri definition.
   *
   * @var String
   */
  public $resource_uri;

  /**
   * The endpoint allowed methods.
   *
   * @var array
   */
  public $methods = [];

  /**
   * List of accepted parameters.
   *
   * @var array
   */
  public $parameters = [];

  /**
   * List of available attributes.
   *
   * @var array
   */
  public $attributes = [];

  /**
   * Available field mapping.
   *
   * @var array
   */
  public $field_mappings = [];

  public $provider = '';

  public $machine_name = '';

  public $entity_type_id = '';
}