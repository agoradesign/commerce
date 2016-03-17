<?php

namespace Drupal\commerce_product\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductAttributeOverviewForm extends FormBase {

  /**
   * The current product attribute.
   *
   * @var \Drupal\commerce_product\Entity\ProductAttributeInterface
   */
  protected $attribute;

  /**
   * The product attribute value storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $valueStorage;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_product_attribute_overview';
  }

  /**
   * Constructs a new ProductAttributeOverviewForm object.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CurrentRouteMatch $current_route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->attribute = $current_route_match->getParameter('commerce_product_attribute');
    $this->valueStorage = $entity_type_manager->getStorage('commerce_product_attribute_value');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();
    $values = $this->attribute->getValues();
    // The value map allows new values to be added and removed before saving.
    // An array in the $index => $id format. $id is '_new' for unsaved values.
    $value_map = $form_state->get('value_map');
    if (empty($value_map)) {
      $value_map = array_keys($values);
      $form_state->set('value_map', $value_map);
    }

    $wrapper_id = Html::getUniqueId('product-attribute-values-ajax-wrapper');
    $form['values'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Value'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'product-attribute-value-order-weight',
        ],
      ],
      '#weight' => 5,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    /** @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface[] $values */
    foreach ($value_map as $index => $id) {
      $value_form = &$form['values'][$index];
      $value_form['#attributes']['class'][] = 'draggable';
      $value_form['#weight'] = isset($user_input['values'][$index]) ? $user_input['values'][$index]['weight'] : NULL;

      $value_form['entity'] = [
        '#type' => 'inline_entity_form',
        '#parents' => ['values', $index, 'entity'],
        '#entity_type' => 'commerce_product_attribute_value',
        '#bundle' => $this->attribute->id(),
        '#save_entity' => FALSE,
      ];
      if ($id == '_new') {
        $value_form['entity']['#op'] = 'add';
        $default_weight = $index;
      }
      else {
        /** @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface $value */
        $value = $values[$id];
        $value_form['entity']['#op'] = 'edit';
        $value_form['entity']['#default_value'] = $value;
        $default_weight = $value->getWeight();
      }

      $value_form['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $default_weight,
        '#attributes' => [
          'class' => ['product-attribute-value-order-weight'],
        ],
      ];
      $value_form['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_value' . $index,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => ['::removeValueSubmit'],
        '#value_index' => $index,
        '#ajax' => [
          'callback' => '::valuesAjax',
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    // Sort the values by weight. Ensures weight is preserved on ajax refresh.
    uasort($form['values'], ['\Drupal\Component\Utility\SortArray', 'sortByWeightProperty']);

    $form['values']['_new'] = [
      '#tree' => FALSE,
    ];
    $form['values']['_new']['type'] = [
      '#prefix' => '<div class="product-attribute-value-new">',
      '#suffix' => '</div>',
    ];
    $form['values']['_new']['type']['add_value'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#submit' => ['::addValueSubmit'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::valuesAjax',
        'wrapper' => $wrapper_id,
      ],
    ];
    $form['values']['_new']['operations'] = [
      'data' => [],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save values'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Ajax callback for value operations.
   */
  public function valuesAjax(array $form, FormStateInterface $form_state) {
    return $form['values'];
  }

  /**
   * Submit callback for adding a new value.
   */
  public function addValueSubmit(array $form, FormStateInterface $form_state) {
    $value_map = (array) $form_state->get('value_map');
    $value_map[] = '_new';
    $form_state->set('value_map', $value_map);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing a value.
   */
  public function removeValueSubmit(array $form, FormStateInterface $form_state) {
    $value_index = $form_state->getTriggeringElement()['#value_index'];
    $value_map = (array) $form_state->get('value_map');
    $value_id = $value_map[$value_index];
    unset($value_map[$value_index]);
    $form_state->set('value_map', $value_map);
    // Non-new values also need to be deleted from storage.
    if ($value_id != '_new') {
      $delete_queue = (array) $form_state->get('delete_queue');
      $delete_queue[] = $value_id;
      $form_state->set('delete_queue', $delete_queue);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $delete_queue = $form_state->get('delete_queue');
    if (!empty($delete_queue)) {
      /** @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface[] $values */
      $values = $this->valueStorage->loadMultiple($delete_queue);
      $this->valueStorage->delete($values);
    }

    foreach ($form_state->getValue(['values']) as $value_data) {
      /** @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface $value */
      $value = $value_data['entity'];
      $value->setWeight($value_data['weight']);
      $value->save();
    }
  }

}
