<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage_views\Plugin\views\query;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\api_storage\ApiEndpointManager;
use Drupal\api_storage\ApiClientStorageManager;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Wrap calls to the api storage in order to expose the results to views.
 *
 * @ingroup views_query_plugins
 *
 * @ViewsQuery(
 *   id = "api_storage",
 *   title = @Translation("Api Storage"),
 *   help = @Translation("Query against the Api storage")
 * )
 */
class ApiStorage extends QueryPluginBase {
  protected $storageClientManager;

  protected $ApiEndpointManager;

  protected $relationships;

  protected $where;
  
  protected $storageClients = [];

  protected $entityManager;

  /**
   * ApiStorage constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\api_storage\ApiClientStorageManager $api_client_storage_manager
   * @param \Drupal\api_storage\ApiEndpointManager $api_endpoint_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              ApiClientStorageManager $api_client_storage_manager,
                              ApiEndpointManager $api_endpoint_manager,
                              EntityManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storageClientManager = $api_client_storage_manager;
    $this->ApiEndpointManager = $api_endpoint_manager;
    $this->entityManager = $entity_manager;
    $this->relationships = [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.api_storage.entity_storage_client'),
      $container->get('plugin.manager.api_storage_endpoint'),
      $container->get('entity.manager')
    );
  }

  protected function getStorageClient($bundle_id) {
    if (!isset($this->storageClients[$bundle_id])) {
      $bundle = $this->entityManager->getStorage('fidelio_entity_type')->load($bundle_id);
      // TODO: clean storage client create instance.
      $config = [
        'http_client' => \Drupal::service('http_client'),
        'decoder' => \Drupal::service('api_storage.entity_storage_client.response_decoder_factory'),
        'endpoint' => $bundle->getEndpoint(),
        'format' => $bundle->getFormat(),
        'http_headers' => [],
        'parameters' => $bundle->getParameters(),
      ];
      $api_key_settings = $bundle->getApiKeySettings();
      if (!empty($api_key_settings['header_name']) && !empty($api_key_settings['key'])) {
        $config['http_headers'][$api_key_settings['header_name']] = $api_key_settings['key'];
      }
      $this->storageClients[$bundle_id] = $this->storageClientManager->createInstance(
        $bundle->getClient(),
        $config
      );
    }

    return $this->storageClients[$bundle_id];
  }

  /**
   * {@inheritdoc}
   */
  public function build(ViewExecutable $view) {
    $this->view = $view;

    $view->initPager();

    $view->pager->query();

    $view->build_info['query'] = $this->query();
    $view->build_info['count_query'] = $this->query(TRUE);
  }

  public function query($get_count = TRUE) {
    $entity_info = $this->getEntityTableInfo();
    $entity_info = reset($entity_info);
    $this->entityManager->getStorage($entity_info['entity_type']);
    $query = $this->entityManager->getStorage($entity_info['entity_type'])->getQuery();
    $bundle_id = str_replace($entity_info['provider'] . '_', '', $entity_info['base']);
    $query->condition('type', $bundle_id);

    if (FALSE && isset($this->where)) {
      foreach ($this->where as $where_group => $where) {
        foreach ($where['conditions'] as $condition) {
          // Remove dot from begining of the string.
//          $field_name = ltrim($condition['field'], '.');
          $field_name = key($condition['value']);
          $query->condition($field_name, reset($condition['value']));
        }
      }
    }

    if (!empty($this->view->argument)) {
      foreach ($this->view->argument as $field_name => $arg) {
        $query->condition($field_name, $arg->argument);
      }
    }

    if ($pager = $this->view->pager) {
      $query->pager($pager->getItemsPerPage(), 0);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    // Set the units according to the setting on the view.
    if (!empty($this->options['accept_lang'])) {
      $this->storageClient->setAcceptLang($this->options['accept_lang']);
    }

    // Grab data regarding conditions placed on the query.
    $query = $view->build_info['query'];
    // Need the data about the table to know which endpoint to use.
    $views_data = Views::viewsData();
    $base_table = $this->view->storage->get('base_table');
    $base_table_data = $views_data->get($base_table);

    if (isset($this->view->filter['type']) && $this->view->filter['type']->value) {
      $bundle_id = reset($this->view->filter['type']->value);
      $entityType = $this->view->filter['type']->getEntityType();
      $storage_client = $this->entityManager->getStorage($entityType);
      $query = $storage_client->getQuery();
      $query->condition('type', $bundle_id);
    }

    $start = microtime(TRUE);

    $parameters = [];
    $index = 0;
    $results = $query->execute();
    foreach ($results as $i => $result) {
      $row = (array) $result;
      // If we got some data back from the API for this user, add defaults and
      // expose as a row to views.
      if (!empty($row)) {
        $results[$i]['index'] = $index++;
        $results[$i]['uid'] = 0;
      }
    }

    if ($view->usePager()) {
      $view->total_rows = $query->getTotalCount();
    }
    else {
      $view->total_rows = count($results);
    }

    $this->loadEntities($results);
    $view->result = $results;

    $view->execute_time = microtime(TRUE) - $start;
  }

  /**
   * Create entities instances filled with result object data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $results
   */
  public function loadEntities(&$results) {
    $entity_information = $this->getEntityTableInfo();
    // No entity tables found, nothing else to do here.
    if (empty($entity_information)) {
      return;
    }

    // Extract all entity types from entity_information.
    $entity_types = array();
    foreach ($entity_information as $info) {
      $entity_type = $info['entity_type'];
      if (!isset($entity_types[$entity_type])) {
        $entity_types[$entity_type] = $this->entityManager->getDefinition($entity_type);
      }

      $entities = $this->entityManager->getStorage($entity_types[$entity_type]->id())->getResultEntities($results);

      foreach ($results as $key => $item) {
        $arr_item = (array) $item;
        //$arr_item['_entity'] = $entities[$key];
        $results[$key] = new ResultRow($arr_item);
        $results[$key]->_entity = $entities[$key];
      }
    }

    return $results;
  }

  /**
   * Add condition to query.
   *
   * @param $group
   * @param $field
   * @param $value
   * @param null $operator
   *
   * @see \Drupal\Core\Database\Query\ConditionInterface::condition()
   * @see \Drupal\Core\Database\Query\Condition
   */
  public function addWhere($group, $field, $value = NULLL, $operator = NULL) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    $this->where[$group]['conditions'][] = [
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['accept_lang'] = array(
      'default' => NULL,
    );

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['accept_lang'] = [
      '#type' => 'select',
      '#options' => $this->storageClient->getAcceptLangOptions(),
      '#title' => $this->t('Unit system'),
      '#default_value' => $this->options['accept_lang'],
      '#description' => $this->t('Set the unit system to use for Fidelio API requests.'),
    ];
  }

  /**
   * Add a relationship. For Fidelio views query backends, a relationship
   * corresponds to a FidelioBaseTableEndpoint plugin_id, which will be used to
   * fetch rows from that endpoint in addition to the base table requested.
   */
  public function addRelationship($endpoint_plugin_id) {
    $this->relationships[] = $endpoint_plugin_id;
  }

  /**
   * Ensures a table exists in the query.
   */
  public function ensureTable($table, $relationship = NULL) {
    return '';
  }

  /**
   * Adds a field to the table. In our case, the Fidelio API has no
   * notion of limiting the fields that come back, so tracking a list
   * of fields to fetch is irrellevant for us. Hence this function body is more
   * or less empty and it serves only to satisfy handlers that may assume an
   * addField method is present b/c they were written against Views' default SQL
   * backend.
   */
  public function addField($table, $field, $alias = '', $params = []) {
    return $field;
  }

  /**
   * Generates a unique placeholder used in the db query.
   */
  public function placeholder($base = 'views') {
    static $placeholders = array();
    if (!isset($placeholders[$base])) {
      $placeholders[$base] = 0;
      return ':' . $base;
    }
    else {
      return ':' . $base . ++$placeholders[$base];
    }
  }

  public function addWhereExpression($group, $snippet, $args = array()) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    $this->where[$group]['conditions'][] = array(
      'field' => $snippet,
      'value' => $args,
      'operator' => 'formula',
    );
  }
}