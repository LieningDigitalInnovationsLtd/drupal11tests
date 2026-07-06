<?php

declare(strict_types=1);

namespace Drupal\media_library_image_crop;

use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\image_widget_crop\ImageWidgetCropInterface;

final class InlineCropSaver {

  public function __construct(
    private readonly ImageWidgetCropInterface $cropManager,
  ) {}

  public function saveFromFormValues(array $values): void {
    $this->walkValues($values);
  }

  private function walkValues(mixed $values): void {
    if (!is_array($values)) {
      return;
    }

    if (isset($values['file-id'], $values['file-uri'], $values['crop_wrapper'])) {
      $this->saveCropElement($values);
      return;
    }

    foreach ($values as $value) {
      $this->walkValues($value);
    }
  }

  private function saveCropElement(array $crop_values): void {
    $field_value = [
      'file-id' => $crop_values['file-id'],
      'file-uri' => $crop_values['file-uri'],
    ];

    foreach ($crop_values['crop_wrapper'] as $crop_type_name => $wrapper) {
      if (!is_array($wrapper)) {
        continue;
      }

      $properties = $wrapper['crop_container']['values'] ?? NULL;
      if (!is_array($properties)) {
        continue;
      }

      $crop_type = CropType::load($crop_type_name);
      if (!$crop_type) {
        continue;
      }

      $crop_exists = Crop::cropExists($field_value['file-uri'], $crop_type_name);

      if (($properties['crop_applied'] ?? '0') === '0') {
        if ($crop_exists) {
          $this->cropManager->deleteCrop(
            $field_value['file-uri'],
            $crop_type,
            $field_value['file-id'],
          );
        }
        continue;
      }

      if (empty($properties['width']) || empty($properties['height'])) {
        continue;
      }

      if (!$crop_exists) {
        $this->cropManager->applyCrop($properties, $field_value, $crop_type);
      }
      else {
        $this->cropManager->updateCrop($properties, $field_value, $crop_type);
      }
    }
  }

}
