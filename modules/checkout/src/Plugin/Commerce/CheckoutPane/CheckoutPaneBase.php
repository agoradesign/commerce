<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Class CheckoutPaneBase.
 */
abstract class CheckoutPaneBase extends PluginBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(FormStateInterface $form_state) {
    // By default a pane is always visible.
    return TRUE;
  }


}
