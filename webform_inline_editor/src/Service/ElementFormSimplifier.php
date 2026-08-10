<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Service;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Simplifies webform element configuration forms.
 */
final class ElementFormSimplifier {

  use StringTranslationTrait;

  public const HELP_HIDDEN = 'hidden';

  public function alterConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $form['#tabs'] = FALSE;
    $form['#attributes']['class'][] = 'webform-inline-editor-element-form';

    $this->simplifyElement($form);
    $this->flattenHelpAndDescription($form);
    $this->simplifyValidation($form);
    $this->simplifyDisplay($form);
    $this->hideAdvanced($form);
  }

  public function alterElementForm(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['properties'])) {
      return;
    }

    $form['#attached']['library'][] = 'webform_inline_editor/element_form';
    $properties = &$form['properties'];

    foreach (['key', 'key_warning', 'table_message'] as $key) {
      if (isset($properties['element'][$key])) {
        $properties['element'][$key]['#access'] = FALSE;
      }
    }

    foreach (['element_description', 'validation', 'form'] as $section) {
      if (isset($properties[$section])) {
        $this->hideEmptySection($properties[$section]);
      }
    }

    if (isset($properties['form']['display_container']) && is_array($properties['form']['display_container'])) {
      $display = &$properties['form']['display_container'];
      if (isset($display['help_display'])) {
        $this->normalizeShowHideDefault($display['help_display'], '', self::HELP_HIDDEN);
      }
      if (isset($display['description_display'])) {
        $this->normalizeShowHideDefault($display['description_display'], '', 'invisible');
      }
    }

    $this->preserveAsValue($properties, 'states', ['conditional_logic', 'states']);
    $this->preserveAsValue($properties, 'states_clear', ['conditional_logic', 'states_clear']);
    unset($properties['conditional_logic']);
    $this->preserveAsValue($properties, 'custom', ['custom', 'properties']);

    foreach (['tabs', 'tab_general', 'tab_conditions', 'tab_advanced', 'tab_access'] as $tab) {
      if (isset($properties[$tab])) {
        $properties[$tab]['#access'] = FALSE;
      }
    }
  }

  public function alterWebformElement(array &$element): void {
    if (($element['#help_display'] ?? '') === self::HELP_HIDDEN) {
      unset($element['#help'], $element['#help_title'], $element['#help_display']);
    }
  }

  private function simplifyElement(array &$form): void {
    if (!isset($form['element']) || !is_array($form['element'])) {
      return;
    }

    $form['element']['#weight'] = -50;
    foreach (Element::children($form['element']) as $key) {
      if ($key !== 'title') {
        $form['element'][$key]['#access'] = FALSE;
      }
    }
  }

  private function flattenHelpAndDescription(array &$form): void {
    if (!isset($form['element_description']) || !is_array($form['element_description'])) {
      return;
    }

    $description = $form['element_description']['description'] ?? NULL;
    $help_details = $form['element_description']['help'] ?? NULL;
    $help_title = is_array($help_details) ? ($help_details['help_title'] ?? NULL) : NULL;
    $help = is_array($help_details) ? ($help_details['help'] ?? NULL) : NULL;
    $help_title_text = is_array($help_details) ? ($help_details['#title'] ?? NULL) : NULL;

    if (isset($form['element_description']['more'])) {
      $form['element_description']['more']['#access'] = FALSE;
    }
    unset($form['element_description']['description'], $form['element_description']['help']);

    if (is_array($description)) {
      $form['description'] = $description;
      $form['description']['#weight'] = -45;
    }

    $form['element_description']['#type'] = 'details';
    if ($help_title_text !== NULL) {
      $form['element_description']['#title'] = $help_title_text;
    }
    $form['element_description']['#open'] = FALSE;
    $form['element_description']['#weight'] = -40;
    unset($form['element_description']['#description'], $form['element_description']['#states']);

    if (is_array($help_title)) {
      $form['element_description']['help_title'] = $help_title;
    }
    if (is_array($help)) {
      $form['element_description']['help'] = $help;
    }

    if (!isset($form['element_description']['help_title']) && !isset($form['element_description']['help'])) {
      $form['element_description']['#access'] = FALSE;
    }
  }

  private function simplifyValidation(array &$form): void {
    if (!isset($form['validation']) || !is_array($form['validation'])) {
      return;
    }

    $form['validation']['#open'] = FALSE;
    $form['validation']['#weight'] = -30;

    if (isset($form['form']['length_container'])) {
      $form['validation']['length_container'] = $form['form']['length_container'];
      unset($form['form']['length_container']);
    }

    foreach (Element::children($form['validation']) as $key) {
      if (!in_array($key, ['required_container', 'length_container'], TRUE)) {
        $form['validation'][$key]['#access'] = FALSE;
      }
    }

    if (isset($form['validation']['required_container']['required_error'])) {
      $form['validation']['required_container']['required_error']['#access'] = FALSE;
    }
  }

  private function simplifyDisplay(array &$form): void {
    if (!isset($form['form']) || !is_array($form['form'])) {
      return;
    }

    $form['form']['#open'] = FALSE;
    $form['form']['#weight'] = -20;

    if (isset($form['form']['display_container']['help_display'])) {
      $this->limitShowHideOptions($form['form']['display_container']['help_display'], '', self::HELP_HIDDEN);
    }
    if (isset($form['form']['display_container']['description_display'])) {
      $this->limitShowHideOptions($form['form']['display_container']['description_display'], '', 'invisible');
    }
    if (isset($form['form']['display_container']['title_display'])) {
      $form['form']['display_container']['title_display']['#access'] = FALSE;
    }
    if (isset($form['form']['title_display_message'])) {
      $form['form']['title_display_message']['#access'] = FALSE;
    }
    if (isset($form['conditional_logic'])) {
      $form['conditional_logic']['#access'] = FALSE;
    }

    foreach (Element::children($form['form']) as $key) {
      if (!in_array($key, ['display_container', 'placeholder'], TRUE)) {
        $form['form'][$key]['#access'] = FALSE;
      }
    }

    if (isset($form['form']['display_container'])) {
      foreach (Element::children($form['form']['display_container']) as $key) {
        if (!in_array($key, ['help_display', 'description_display'], TRUE)) {
          $form['form']['display_container'][$key]['#access'] = FALSE;
        }
      }
    }
  }

  private function limitShowHideOptions(array &$element, string $show, string $hide): void {
    $element['#options'] = [
      $show => $this->t('Show'),
      $hide => $this->t('Hide'),
    ];
    unset($element['#empty_option'], $element['#description']);
  }

  private function normalizeShowHideDefault(array &$element, string $show, string $hide): void {
    if (!array_key_exists('#default_value', $element)) {
      return;
    }
    $current = (string) $element['#default_value'];
    if (!array_key_exists($current, $element['#options'] ?? [])) {
      $element['#default_value'] = ($current === $hide) ? $hide : $show;
    }
  }

  private function preserveAsValue(array &$properties, string $key, array $path): void {
    $element = $properties;
    foreach ($path as $segment) {
      if (!isset($element[$segment]) || !is_array($element[$segment])) {
        return;
      }
      $element = $element[$segment];
    }
    $properties[$key] = [
      '#type' => 'value',
      '#value' => $element['#default_value'] ?? $element['#value'] ?? NULL,
    ];
  }

  private function hideAdvanced(array &$form): void {
    foreach ([
      'default',
      'multiple',
      'wrapper_attributes',
      'element_attributes',
      'label_attributes',
      'summary_attributes',
      'title_attributes',
      'display',
      'admin',
      'options',
      'options_other',
      'options_properties',
      'access',
      'flex',
      'custom',
    ] as $key) {
      if (isset($form[$key])) {
        $form[$key]['#access'] = FALSE;
      }
    }
  }

  private function hideEmptySection(array &$element): void {
    foreach (Element::children($element) as $key) {
      if (($element[$key]['#access'] ?? TRUE) === FALSE) {
        continue;
      }
      $nested = Element::children($element[$key]);
      if (!$nested) {
        return;
      }
      foreach ($nested as $nested_key) {
        if (($element[$key][$nested_key]['#access'] ?? TRUE) !== FALSE) {
          return;
        }
      }
    }
    $element['#access'] = FALSE;
  }

}
