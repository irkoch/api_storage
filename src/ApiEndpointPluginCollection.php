<?php
/**
 * @file
 * Contains
 */
namespace Drupal\api_storage;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a collection of endpoint plugins.
 */
class ApiEndpointPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * The endpoint ID this plugin collection belongs to.
   *
   * @var string
   */
  protected $endpointId;

  /**
   * Constructs a new endpointPluginCollection.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   * @param string $instance_id
   *   The ID of the plugin instance.
   * @param array $configuration
   *   An array of configuration.
   * @param string $endpoint_id
   *   The unique ID of the endpoint entity using this plugin.
   */
  public function __construct(PluginManagerInterface $manager, $instance_id, array $configuration, $endpoint_id) {
    parent::__construct($manager, $instance_id, $configuration);

    $this->endpointId = $endpoint_id;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\api_storage\ApiEndpointPluginInterface
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    if (!$instance_id) {
      throw new PluginException("The endpoint '{$this->endpointId}' did not specify a plugin.");
    }

    try {
      parent::initializePlugin($instance_id);
    }
    catch (PluginException $e) {
      $module = $this->configuration['provider'];
      // Ignore endpoints belonging to uninstalled modules, but re-throw valid
      // exceptions when the module is installed and the plugin is
      // misconfigured.
      if (!$module || \Drupal::moduleHandler()->moduleExists($module)) {
        throw $e;
      }
    }
  }

}
