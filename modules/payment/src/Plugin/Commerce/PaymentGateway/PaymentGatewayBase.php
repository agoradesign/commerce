<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base payment gateway class.
 */
abstract class PaymentGatewayBase extends PluginBase implements PaymentGatewayInterface, ContainerFactoryPluginInterface {

  /**
   * The payment type handled by the gateway.
   *
   * @var \Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeInterface
   */
  protected $paymentType;

  /**
   * Constructs a new PaymentGatewayBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PaymentTypeManager $payment_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    // Instantiate the payment type right away to ensure that the ID is valid.
    $this->paymentType = $payment_type_manager->createInstance($this->pluginDefinition['payment_type']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_payment_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    return $this->configuration['display_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentType() {
    return $this->paymentType;
  }

  /**
   * {@inheritdoc}
   */
  public function getMode() {
    return $this->configuration['mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedModes() {
    return [
      'test' => $this->t('Test'),
      'live' => $this->t('Live'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $modes = $this->getSupportedModes();

    return [
      'display_label' => $this->paymentType->getLabel(),
      'mode' => $modes ? reset($modes) : '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['display_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display label'),
      '#description' => $this->t('Shown to the customer.'),
      '#maxlength' => 255,
      '#default_value' => $this->configuration['display_label'],
      '#required' => TRUE,
    ];
    if ($modes = $this->getSupportedModes()) {
      $form['mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Mode'),
        '#options' => $modes,
        '#default_value' => $this->configuration['mode'],
        '#required' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['display_label'] = $values['display_label'];
      $this->configuration['mode'] = $values['mode'];
    }
  }

}
