<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Component\Plugin\PluginBase;

abstract class ApiClientStorageBase extends PluginBase implements ApiClientStorageInterface {
  /**
   * The HTTP client to fetch the data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The decoder to decode the data.
   *
   * @var \Drupal\api_storage\ResponseDecoderFactoryInterface
   */
  protected $decoder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $this->configuration['http_client'];
    $this->decoder = $this->configuration['decoder'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpHeaders() {
    $headers = [
      'X-Api-Key' => \Drupal::config('api_storage.settings')->get($this->getBundle()->getEntityTypeId() . '_access_token'),
      'Accept'    => 'application/json'
    ];

    if (isset($this->configuration['http_headers'])) {
      $headers = array_merge($this->configuration['http_headers'], $headers);
    }

    return $headers;
  }

  /**
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  public function getConfig() {
    return \Drupal::config('api_storage.settings');
  }

  /**
   * @return mixed
   */
  public function getResourcesUrl() {
    $resources_url_key = $this->getBundle()->getEntityTypeId() . '_resources_url';
    $resources_url = $this->getConfig()->get($resources_url_key);

//    $bundle_plugin = $this->getBundle()->getPlugin();
//    if ($bundle_plugin && method_exists($bundle_plugin, 'resourceUrlAlter')) {
//      $resources_url = $bundle_plugin->resourceUrlAlter($resources_url);
//    }
    if (!$resources_url) {
      $resources_url = $plugin_definition['resources_url'][$resources_url_key];
    }

    return $resources_url;
  }

  /**
   * @return \Drupal\Core\Entity\EntityTypeInterface
   */
  protected function getBundle() {
    return $this->configuration['bundle'];
  }
}