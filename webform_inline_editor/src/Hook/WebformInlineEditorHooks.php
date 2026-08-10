<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Hook;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Form\NodeForm;
use Drupal\webform\WebformInterface;
use Drupal\webform_inline_editor\Service\ElementFormSimplifier;

/**
 * Hook implementations for webform_inline_editor.
 */
final class WebformInlineEditorHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ElementFormSimplifier $elementFormSimplifier,
  ) {}

  /**
   * Implements hook_webform_element_configuration_form_alter().
   */
  #[Hook('webform_element_configuration_form_alter')]
  public function webformElementConfigurationFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->elementFormSimplifier->alterConfigurationForm($form, $form_state);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for webform_ui_element_form.
   */
  #[Hook('form_webform_ui_element_form_alter')]
  public function formWebformUiElementFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->elementFormSimplifier->alterElementForm($form, $form_state);
  }

  /**
   * Implements hook_webform_element_alter().
   */
  #[Hook('webform_element_alter')]
  public function webformElementAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $this->elementFormSimplifier->alterWebformElement($element);
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$complete_form, FormStateInterface $form_state, array $context): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof NodeForm) {
      return;
    }

    $definition = $context['items']->getFieldDefinition();
    $type = $definition->getType();
    if ($type !== 'webform' && !($type === 'entity_reference' && $definition->getSetting('target_type') === 'webform')) {
      return;
    }

    $field_name = $definition->getName();
    $webform = $this->loadReferencedWebform($form_object->getEntity(), $field_name, $form_state);
    if (!$webform || !$webform->access('update')) {
      return;
    }

    $complete_form['#attributes']['class'][] = 'webform-inline-editor';
    $complete_form['webform_inline_editor'] = [
      '#type' => 'container',
      '#weight' => -1000,
      '#attributes' => ['class' => ['webform-inline-editor__card']],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['webform-inline-editor__label']],
        '#value' => $webform->label(),
      ],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['webform-inline-editor__summary']],
        '#value' => $this->summary($webform),
      ],
      'open' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Edit webform'),
        '#attributes' => [
          'type' => 'button',
          'class' => ['button', 'button--primary', 'button--small', 'js-webform-inline-editor-open'],
          'data-dialog-title' => (string) $this->t('Edit @label', ['@label' => $webform->label()]),
          'data-editor-url' => Url::fromRoute('webform_inline_editor.embed', [
            'webform' => $webform->id(),
          ])->toString(),
        ],
      ],
    ];
    $complete_form['#attached']['library'][] = 'webform_inline_editor/node';

    $this->replaceWidgetWithValues($complete_form, $context['items'], $webform->id());
  }

  /**
   * Implements hook_preprocess_html().
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if (!$this->isEmbedRoute()) {
      return;
    }
    $variables['html_attributes']['class'][] = 'webform-inline-editor-embed';
    $variables['attributes']['class'][] = 'webform-inline-editor-embed';
  }

  /**
   * Implements hook_toolbar_alter().
   */
  #[Hook('toolbar_alter')]
  public function toolbarAlter(array &$items): void {
    if ($this->isEmbedRoute()) {
      $items = [];
    }
  }

  private function isEmbedRoute(): bool {
    return \Drupal::routeMatch()->getRouteName() === 'webform_inline_editor.embed';
  }

  private function loadReferencedWebform($entity, string $field_name, FormStateInterface $form_state): ?WebformInterface {
    $webform_id = NULL;
    $value = $form_state->getValue($field_name);
    if (is_array($value) && !empty($value[0]['target_id'])) {
      $webform_id = (string) $value[0]['target_id'];
    }
    elseif (!empty($value['target_id'])) {
      $webform_id = (string) $value['target_id'];
    }
    elseif ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $webform_id = (string) $entity->get($field_name)->target_id;
    }

    if (!$webform_id) {
      return NULL;
    }

    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    return $webform instanceof WebformInterface ? $webform : NULL;
  }

  private function replaceWidgetWithValues(array &$complete_form, $items, string $webform_id): void {
    if (!isset($complete_form['widget'])) {
      return;
    }

    foreach (Element::children($complete_form['widget']) as $delta) {
      $item = $items[$delta] ?? NULL;
      $complete_form['widget'][$delta]['target_id'] = [
        '#type' => 'value',
        '#value' => $webform_id,
      ];
      $complete_form['widget'][$delta]['settings'] = [
        '#tree' => TRUE,
        '#access' => FALSE,
        'status' => [
          '#type' => 'value',
          '#value' => $item?->status ?? WebformInterface::STATUS_OPEN,
        ],
        'default_data' => [
          '#type' => 'value',
          '#value' => $item?->default_data,
        ],
        'scheduled' => [
          'open' => [
            '#type' => 'value',
            '#value' => !empty($item?->open)
              ? DrupalDateTime::createFromTimestamp((int) strtotime((string) $item->open))
              : NULL,
          ],
          'close' => [
            '#type' => 'value',
            '#value' => !empty($item?->close)
              ? DrupalDateTime::createFromTimestamp((int) strtotime((string) $item->close))
              : NULL,
          ],
        ],
      ];
    }
  }

  private function summary(WebformInterface $webform): string {
    $elements = $webform->getElementsDecoded();
    return (string) $this->t('@count elements · @status', [
      '@count' => is_array($elements) ? count($elements) : 0,
      '@status' => $webform->isOpen() ? $this->t('Open') : $this->t('Closed'),
    ]);
  }

}
