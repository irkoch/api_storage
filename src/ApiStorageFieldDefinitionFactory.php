<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Core\Field\BaseFieldDefinition;

class ApiStorageFieldDefinitionFactory {
  protected $definition;

  public function __construct($definition) {
    $this->definition = $definition;
  }

  public static function create($definition) {
    return (new static($definition))->getBaseFieldDefinition();
  }

  protected function getBaseFieldDefinition() {
    return BaseFieldDefinition::create($this->getType())
      ->setLabel($this->getLabel())
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setDefaultValue('')
      ->setSettings($this->getSettings())
      ->setDisplayOptions('view', $this->getDisplayOptions('view'))
      ->setDisplayOptions('form', $this->getDisplayOptions('form'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setCardinality($this->getCardinality());
  }

  protected function getType() {
    return $this->definition['type'];
  }

  protected function getLabel() {
    return isset($this->definition['label']) ? t($this->definition['label']) : $this->definition['name'];
  }

  protected function getCardinality() {
    return isset($this->definition['cardinality']) ? (int) $this->definition['cardinality'] : 1;
  }

  protected function getSettings() {
    switch ($this->getType()) {
      case 'string':
        $settings = ['max_length' => 255];
        break;
      default:
        $settings = [];
    }

    return $settings;
  }

  protected function getDisplayOptions($context) {
    $weight = isset($this->definition['weight']) ? $this->definition['weight'] : 0;
    $default_options = [
      'view' => [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => $weight
      ],
      'form' => [
        'type' => 'string_textfield',
        'weight' => $weight
      ]
    ];

    $options = [];
    switch($this->getType()) {
      case 'decimal':
        $options = [
          'form' => [
            'type' => 'number',
          ]
        ];
        break;
      case 'integer':
        $options = [
          'view' => [
            'label' => 'above',
            'type' => 'string',
          ],
          'form' => [
            'type' => 'number',
          ]
        ];
        break;
    }

    if (isset($options[$context])) {
      return array_replace_recursive($default_options[$context], $options[$context]);
    }

    return $default_options[$context];
  }
}