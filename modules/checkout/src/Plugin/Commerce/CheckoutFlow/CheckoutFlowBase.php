<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the base checkout flow class.
 */
abstract class CheckoutFlowBase extends PluginBase implements CheckoutFlowInterface, ContainerFactoryPluginInterface {

  use LinkGeneratorTrait;
  use RedirectDestinationTrait;
  use UrlGeneratorTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a CheckoutPaneBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, RouteMatchInterface $route_match, AccountInterface $account) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->routeMatch = $route_match;
    $this->currentUser = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('current_route_match'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $checkout_flow = self::getCheckoutFlow($this->order);
    $panes = $this->checkoutFlowManager->getCheckoutFlowStepPanes($checkout_flow, $this->step);

    $form += $this->buildFormPanes($checkout_flow, $form, $form_state);

    // Build the form.
    $form['actions'] = $this->actions($panes, $form_state);

    $form['#title'] = $this->t('Checkout @step', ['@step' => $this->step]);

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->get('order');
    $order->save();

    $form_state->setRedirect($this->getRouteName(), [
      'commerce_order' => $this->order->id(),
      'step' => $this->getNextStep(),
    ]);

    if ($this->getNextStep() == $this->getLastStep()) {
      // @todo Invoke event for checkout complete.
    }
  }

  /**
   * Returns an array for Form API definitions for each pane in a step.
   *
   * @param $checkout_flow
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildFormPanes($checkout_flow, array $form, FormStateInterface $form_state) {
    $panes = $this->checkoutFlowManager->getCheckoutFlowStepPanes($checkout_flow, $this->step);
    $form_panes = [];

    foreach ($panes as $pane_id => $pane) {
      if ($pane->isVisible($form_state)) {
        $form_panes[$pane_id] = $pane->buildForm($form, $form_state);
      }
    }

    // If our step did not have any visible panes, recurse with next step.
    if (empty($form_panes)) {
      $this->step = $this->getNextStep();
      $form_panes = $this->buildFormPanes($checkout_flow, $form, $form_state);
    }

    return $form_panes;
  }

  /**
   * {@inheritdoc}
   */
  public function previousForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect($this->getRouteName(), [
      'commerce_order' => $this->order->id(),
      'step' => $this->getPreviousStep(),
    ]);
  }

  /**
   * Generates action elements for navigating between the operation steps.
   *
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface[] $panes
   *   The current step's panes.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   Form API array of actions
   */
  protected function actions(array $panes, FormStateInterface $form_state) {
    $actions = [
      '#type' => 'actions',
    ];
    if ($this->step != $this->getFirstStep() && $this->step != $this->getLastStep()) {
      $actions['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Previous'),
        '#submit' => [
          '::previousForm',
        ],
      ];
    }

    if ($this->step != $this->getLastStep()) {
      $actions['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
        '#validate' => [],
        '#submit' => [],
      ];

      // Add each pane's validation and submit handler.
      foreach ($panes as $pane_id => $pane) {
        $actions['submit']['#validate'][] = [$pane, 'validateForm'];
        $actions['submit']['#submit'][] = [$pane, 'submitForm'];
      }
      $actions['submit']['#validate'][] = '::validateForm';
      $actions['submit']['#submit'][] = '::submitForm';

      $actions['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute($this->cancelRoute()),
      ];
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelRoute() {
    return 'commerce_cart.page';
  }

  /**
   * Gets the specified checkout step.
   *
   * @return string
   */
  protected function getStep($position) {
    $checkout_flow_plugin_id = self::getCheckoutFlow($this->order);
    $checkout_flow_steps = $this->checkoutFlowManager
      ->getCheckoutFlowSteps($checkout_flow_plugin_id);

    switch ($position) {
      case 'last':
        return key(array_slice($checkout_flow_steps, -1, 1, TRUE));

      case 'next':
        $next_steps = array_slice($checkout_flow_steps,
          array_search($this->step, array_keys($checkout_flow_steps)) + 1);
        // @todo what if this was run on last step.
        return key($next_steps);

      case 'previous':
        $next_steps = array_slice($checkout_flow_steps, array_search($this->step,
            array_keys($checkout_flow_steps)) - 1);
        return key($next_steps);

      case 'first':
      default:
        return key($checkout_flow_steps);
    }
  }

  /**
   * Gets the order's first step in checkout.
   *
   * @return string
   */
  protected function getFirstStep() {
    return $this->getStep('first');
  }

  /**
   * Gets the order's last step in checkout.
   *
   * @return string
   */
  protected function getLastStep() {
    return $this->getStep('last');
  }

  /**
   * Gets the order's next step in checkout.
   *
   * @return string
   */
  protected function getNextStep() {
    return $this->getStep('next');
  }

  /**
   * Gets the order's previous step in checkout.
   *
   * @return string
   */
  protected function getPreviousStep() {
    return $this->getStep('previous');
  }

}
