<?php

/**
 * @file
 * Contains \Drupal\textimage\Form\FlushAllForm.
 */

namespace Drupal\textimage\Form;

use Drupal\Core\Form\ConfirmFormBase;

/**
 * Creates a form to delete an image style.
 */
class FlushAllForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'textimage_flush_all';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Cleanup Textimage?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will remove all image files generated via Textimage, flush all the Textimage image styles, and clear all image entries cached and stored in the database.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Proceed');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array('route_name' => 'textimage.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    _textimage_flush_all();
    $form_state['redirect'] = 'admin/config/media/textimage';
  }

}
