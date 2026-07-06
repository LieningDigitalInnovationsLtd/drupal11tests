<?php

declare(strict_types=1);

namespace Drupal\media_library_image_crop\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\Element;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Medienbibliothek-Widget mit Inline-Zuschnitt nach der Auswahl
 *
 * @FieldWidget(
 *   id = "media_library_image_crop",
 *   label = @Translation("Media Library Image Crop"),
 *   description = @Translation("Medien aus der Bibliothek wählen und im Feld zuschneiden."),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE,
 * )
 */
class MediaLibraryImageCropWidget extends MediaLibraryWidget {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    array $third_party_settings,
    $entity_type_manager,
    $current_user,
    $module_handler,
    protected ImageFactory $imageFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $entity_type_manager,
      $current_user,
      $module_handler,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('image.factory'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'image_style' => '',
      'crop_types' => [],
      'crop_types_required' => [],
      'crop_preview_image_style' => 'crop_thumbnail',
      'show_crop_area' => FALSE,
      'show_default_crop' => TRUE,
      'warn_multiple_usages' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $image_styles = ImageStyle::loadMultiple();
    $image_style_options = ['' => $this->t('- Keine -')];
    foreach ($image_styles as $id => $style) {
      $image_style_options[$id] = $style->label();
    }

    $elements['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Bildstil'),
      '#description' => $this->t('Bildstil für den kontextbezogenen Zuschnitt. Muss einen Manual-crop-Effekt enthalten.'),
      '#options' => $image_style_options,
      '#default_value' => $this->getSetting('image_style'),
      '#required' => TRUE,
    ];

    $crop_type_options = [];
    foreach (CropType::loadMultiple() as $id => $crop_type) {
      $crop_type_options[$id] = $crop_type->label();
    }

    $elements['crop_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Zuschnittformate'),
      '#description' => $this->t('Zuschnittformate, die nach der Medienauswahl inline angezeigt werden.'),
      '#options' => $crop_type_options,
      '#default_value' => $this->getSetting('crop_types'),
    ];

    $elements['crop_types_required'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Pflicht-Zuschnittformate'),
      '#description' => $this->t('Ausgewählte Formate müssen vor dem Speichern angewendet werden.'),
      '#options' => $crop_type_options,
      '#default_value' => $this->getSetting('crop_types_required'),
    ];

    $elements['show_crop_area'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Zuschnittbereich immer geöffnet'),
      '#default_value' => $this->getSetting('show_crop_area'),
    ];

    $elements['show_default_crop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Standard-Zuschnitt anzeigen'),
      '#default_value' => $this->getSetting('show_default_crop'),
    ];

    $elements['warn_multiple_usages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bei mehrfacher Verwendung warnen'),
      '#default_value' => $this->getSetting('warn_multiple_usages'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    if ($style_id = $this->getSetting('image_style')) {
      $style = ImageStyle::load($style_id);
      $summary[] = $this->t('Bildstil: @style', [
        '@style' => $style ? $style->label() : $style_id,
      ]);
    }

    $crop_types = array_filter($this->getSetting('crop_types') ?? []);
    if ($crop_types) {
      $labels = [];
      foreach ($crop_types as $crop_type_id) {
        $crop_type = CropType::load($crop_type_id);
        $labels[] = $crop_type ? $crop_type->label() : $crop_type_id;
      }
      $summary[] = $this->t('Inline-Zuschnittformate: @types', [
        '@types' => implode(', ', $labels),
      ]);
    }

    $required_crop_types = array_filter($this->getSetting('crop_types_required') ?? []);
    if ($required_crop_types) {
      $labels = [];
      foreach ($required_crop_types as $crop_type_id) {
        $crop_type = CropType::load($crop_type_id);
        $labels[] = $crop_type ? $crop_type->label() : $crop_type_id;
      }
      $summary[] = $this->t('Pflicht-Zuschnittformate: @types', [
        '@types' => implode(', ', $labels),
      ]);
    }

    $summary[] = $this->t('Zuschnittbereich geöffnet: @value', [
      '@value' => $this->getSetting('show_crop_area') ? $this->t('Ja') : $this->t('Nein'),
    ]);
    $summary[] = $this->t('Standard-Zuschnitt: @value', [
      '@value' => $this->getSetting('show_default_crop') ? $this->t('Ja') : $this->t('Nein'),
    ]);
    $summary[] = $this->t('Warnung bei Mehrfachverwendung: @value', [
      '@value' => $this->getSetting('warn_multiple_usages') ? $this->t('Ja') : $this->t('Nein'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $crop_types = array_values(array_filter($this->getSetting('crop_types') ?? []));
    if ($crop_types === []) {
      return $element;
    }

    $crop_types_required = array_values(array_intersect(
      $crop_types,
      array_filter($this->getSetting('crop_types_required') ?? []),
    ));

    $form_state->set('crop_context', $crop_types);
    $element['#attached']['library'][] = 'media_library_image_crop/inline_crop';

    $referenced_entities = $items->referencedEntities();
    if ($referenced_entities === []) {
      return $element;
    }

    foreach ($referenced_entities as $selection_delta => $media_item) {
      if (!$media_item instanceof MediaInterface) {
        continue;
      }

      $file = $this->getImageFile($media_item);
      if (!$file) {
        continue;
      }

      unset($element['selection'][$selection_delta]['edit_ajax_link']);

      if (isset($element['selection'][$selection_delta]['rendered_entity'])) {
        $element['selection'][$selection_delta]['rendered_entity']['#access'] = FALSE;
      }

      $crop_type_labels = [];
      foreach ($crop_types as $type_id) {
        $crop_type = CropType::load($type_id);
        $label = $crop_type ? $crop_type->label() : $type_id;
        if (in_array($type_id, $crop_types_required, TRUE)) {
          $label = (string) $this->t('@label (Pflicht)', ['@label' => $label]);
        }
        $crop_type_labels[$type_id] = $label;
      }

      $image = $this->imageFactory->get($file->getFileUri());
      $preview = $this->buildPreviewRenderArray(
        $file,
        $media_item,
        $crop_types,
        $image,
      );

      $element['selection'][$selection_delta]['inline_crop'] = [
        '#theme' => 'media_library_image_crop_inline',
        '#id' => Html::getUniqueId('media-library-image-crop-inline'),
        'preview' => $preview,
        'crop_element' => [
          '#type' => 'image_crop',
          '#file' => $file,
          '#crop_type_list' => $crop_types,
          '#crop_preview_image_style' => $this->getSetting('crop_preview_image_style'),
          '#crop_types_required' => $crop_types_required,
          '#show_default_crop' => (bool) $this->getSetting('show_default_crop'),
          '#show_crop_area' => (bool) $this->getSetting('show_crop_area'),
          '#warn_multiple_usages' => (bool) $this->getSetting('warn_multiple_usages'),
          '#after_build' => [[static::class, 'afterBuildInlineCropElement']],
        ],
        'crop_type_select' => [
          '#type' => 'select',
          '#title' => $this->t('Format'),
          '#title_display' => 'invisible',
          '#options' => $crop_type_labels,
          '#default_value' => $crop_types[0],
          '#attributes' => [
            'class' => ['media-library-image-crop__crop-type-select'],
          ],
          '#wrapper_attributes' => [
            'class' => ['media-library-image-crop__crop-type'],
          ],
        ],
        '#labels' => [
          'crop' => $this->t('Zuschneiden'),
          'reset' => $this->t('Zurücksetzen'),
          'apply' => $this->t('Anwenden'),
          'crop_type' => $this->t('Format'),
        ],
        '#weight' => 20,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function openMediaLibrary(array $form, FormStateInterface $form_state) {
    $response = parent::openMediaLibrary($form, $form_state);

    if ($crop_context = $form_state->get('crop_context')) {
      \Drupal::service('tempstore.private')
        ->get('contextual_image_widget_crop')
        ->set('crop_context', $crop_context);
    }

    return $response;
  }

  public static function submitInlineCropValues(array &$form, FormStateInterface $form_state): void {
    \Drupal::service('media_library_image_crop.inline_crop_saver')
      ->saveFromFormValues($form_state->getValues());
  }

  public static function afterBuildInlineCropElement(array $element, FormStateInterface $form_state): array {
    if (isset($element['crop_reuse'])) {
      $element['crop_reuse']['#markup'] = (string) t('Diese Zuschnitt-Definition betrifft weitere Verwendungen dieses Bildes.');
    }

    if (isset($element['crop_wrapper']) && !empty($element['#show_crop_area'])) {
      $element['crop_wrapper']['#open'] = TRUE;
      foreach (Element::children($element['crop_wrapper']) as $key) {
        if (($element['crop_wrapper'][$key]['#type'] ?? '') === 'details' && isset($element['crop_wrapper'][$key]['crop_container'])) {
          $element['crop_wrapper'][$key]['#open'] = TRUE;
        }
      }
    }

    return $element;
  }

  protected function getImageFile(MediaInterface $media): ?FileInterface {
    $source = $media->getSource();
    if (!$source instanceof Image) {
      return NULL;
    }

    $source_field = $source->getConfiguration()['source_field'];
    return $media->get($source_field)->entity;
  }

  protected function buildPreviewRenderArray(
    FileInterface $file,
    MediaInterface $media,
    array $crop_types,
    \Drupal\Core\Image\ImageInterface $image,
  ): array {
    $attributes = [
      'class' => ['media-library-image-crop__preview-image'],
      'data-media-library-image-crop-full-src' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
    ];

    $style_id = $this->getSetting('image_style');
    if ($style_id && ImageStyle::load($style_id) && $this->hasAppliedCrop($file, $crop_types)) {
      return [
        '#theme' => 'image_style',
        '#style_name' => $style_id,
        '#uri' => $file->getFileUri(),
        '#alt' => $media->label(),
        '#attributes' => $attributes,
      ];
    }

    return [
      '#theme' => 'image',
      '#uri' => $file->getFileUri(),
      '#width' => $image->getWidth(),
      '#height' => $image->getHeight(),
      '#alt' => $media->label(),
      '#attributes' => $attributes,
    ];
  }

  protected function hasAppliedCrop(FileInterface $file, array $crop_types): bool {
    foreach ($crop_types as $crop_type_id) {
      if (Crop::findCrop($file->getFileUri(), $crop_type_id)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
