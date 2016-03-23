<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides checkout complete message.
 *
 * @CheckoutPane(
 *   id = "complete_message",
 *   label = "Completion message"
 * )
 */
class Complete extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'complete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['complete'] = [
      '#markup' => $this->t('You did it!'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // ?!
  }

}
