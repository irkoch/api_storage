<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Component\Plugin\PluginInspectionInterface;

interface ApiEndpointPluginInterface extends PluginInspectionInterface {
  public function processData(&$data);
}