<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
        '#value' => $this->formatPlural(count($webform->getElementsDecoded() ?: []), '1 element', '@count elements'),
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

    $this->hideWidget($complete_form, $webform->id());
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
   * Implements hook_menu_local_actions_alter().
   */
  #[Hook('menu_local_actions_alter')]
  public function menuLocalActionsAlter(array &$local_actions): void {
    foreach ($local_actions as &$local_action) {
      if (in_array('entity.webform.edit_form', $local_action['appears_on'] ?? [], TRUE)) {
        $local_action['appears_on'][] = 'webform_inline_editor.embed';
      }
    }
  }

  /**
   * Implements hook_preprocess_menu_local_action().
   *
   * @see webform_ui_preprocess_menu_local_action()
   */
  #[Hook('preprocess_menu_local_action')]
  public function preprocessMenuLocalAction(array &$variables): void {
    if (!$this->isEmbedRoute()) {
      return;
    }

    $url = $variables['link']['#url'];
    $secondary = ['entity.webform_ui.element.add_page', 'entity.webform_ui.element.add_layout'];
    if ($url->isRouted() && in_array($url->getRouteName(), $secondary, TRUE)) {
      $variables['link']['#options']['attributes']['class'][] = 'button--secondary';
    }
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

    /**
   * Implements hook_link_alter().
   */
  #[Hook('link_alter')]
  public function linkAlter(array &$variables): void {
    $url = $variables['url'] ?? NULL;
    if (!$url instanceof Url || !$url->isRouted() || $url->getRouteName() !== 'entity.webform.duplicate_form') {
      return;
    }

    if (!empty($url->getOption('query')['template'])) {
      return;
    }

    $webform_id = $url->getRouteParameters()['webform'] ?? NULL;
    if (!$webform_id) {
      return;
    }

    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    if (!$webform instanceof WebformInterface || !$webform->isTemplate()) {
      return;
    }

    $variables['url'] = Url::fromRoute('webform_inline_editor.template_create', [
      'webform' => $webform->id(),
    ]);

    $variables['options']['attributes'] ??= [];
    $attributes = &$variables['options']['attributes'];
    unset($attributes['data-dialog-type'], $attributes['data-dialog-options'], $attributes['data-dialog-renderer']);
    if (isset($attributes['class']) && is_array($attributes['class'])) {
      $attributes['class'] = array_values(array_diff($attributes['class'], ['webform-ajax-link', 'use-ajax']));
    }
  }

  private function isEmbedRoute(): bool {
    return \Drupal::routeMatch()->getRouteName() === 'webform_inline_editor.embed';
  }

  private function loadReferencedWebform(FieldableEntityInterface $entity, string $field_name, FormStateInterface $form_state): ?WebformInterface {
    $value = $form_state->getValue($field_name);
    $webform_id = is_array($value) ? ($value[0]['target_id'] ?? $value['target_id'] ?? NULL) : NULL;

    if (!$webform_id && $entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $webform_id = $entity->get($field_name)->target_id;
    }

    $webform = $webform_id
      ? $this->entityTypeManager->getStorage('webform')->load($webform_id)
      : NULL;

    return $webform instanceof WebformInterface ? $webform : NULL;
  }

  private function hideWidget(array &$complete_form, string $webform_id): void {
    if (!isset($complete_form['widget'])) {
      return;
    }

    foreach (Element::children($complete_form['widget']) as $delta) {
      $complete_form['widget'][$delta]['target_id'] = [
        '#type' => 'value',
        '#value' => $webform_id,
      ];
      $complete_form['widget'][$delta]['settings']['#access'] = FALSE;
    }
  }

}
