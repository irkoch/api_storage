<?php
/**
 * @file
 */

namespace Drupal\api_storage\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ApiStorageEntityForm
 */
class ApiStorageEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if ('edit' == $this->operation) {
      $form['#title'] = $this->t(
        '<em>Edit @type</em> @title',
        [
          '@type' => $this->entityManager->getStorage(
            $this->entity->getEntityType()->getBundleEntityType()
            )->load($this->entity->bundle())->label(),
          '@title' => $this->entity->label()
        ]
      );
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $fidelio_entity = $this->entity;
    if($this->entity->access('view')) {
      $form_state->setRedirect(
        'entity.fidelio_entity.canonical',
        ['fidelio_entity' => $this->entity->id()]
      );
    }
    else {
      $form_state->setRedirect('<front>');
    }

    //return parent::save($form, $form_state);
  }
}