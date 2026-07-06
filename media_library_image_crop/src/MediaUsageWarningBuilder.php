<?php

declare(strict_types=1);

namespace Drupal\media_library_image_crop;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;

final class MediaUsageWarningBuilder {

  use StringTranslationTrait;

  /**
   * @param array<int, array<string, mixed>> $usages
   */
  public function buildSingleWarning(array $usages): array {
    if ($usages === []) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['messages', 'messages--warning', 'media-library-image-crop-usage-warning'],
      ],
      'heading' => [
        '#markup' => '<strong>' . $this->t('Dieses Medium wird derzeit verwendet.') . '</strong>',
      ],
      'intro' => [
        '#markup' => '<p>' . $this->t('Das Medium ist an folgenden Stellen referenziert:') . '</p>',
      ],
      'list' => $this->buildUsageList($usages),
    ];
  }

  /**
   * @param array<int, array<int, array<string, mixed>>> $usages_by_media
   * @param array<int, string> $media_labels
   */
  public function buildBulkWarning(array $usages_by_media, array $media_labels): array {
    $used_media = array_filter($usages_by_media);
    if ($used_media === []) {
      return [];
    }

    $items = [];
    foreach ($used_media as $media_id => $usages) {
      $label = $media_labels[$media_id] ?? (string) $media_id;
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['media-library-image-crop-usage-warning__item']],
        'title' => [
          '#markup' => '<strong>' . $this->t('Medium: @label', ['@label' => $label]) . '</strong>',
        ],
        'list' => $this->buildUsageList($usages),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['messages', 'messages--warning', 'media-library-image-crop-usage-warning', 'media-library-image-crop-usage-warning--bulk'],
      ],
      'heading' => [
        '#markup' => '<strong>' . $this->t('Einige der ausgewählten Medien werden derzeit verwendet.') . '</strong>',
      ],
      'intro' => [
        '#markup' => '<p>' . $this->t('Die folgenden Medien sind an anderer Stellen referenziert:') . '</p>',
      ],
      'items' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['media-library-image-crop-usage-warning__media-list']],
      ],
    ];
  }

  /**
   * @param array<int, array<string, mixed>> $usages
   */
  private function buildUsageList(array $usages): array {
    $items = [];
    foreach ($usages as $usage) {
      $text = $this->formatUsageLabel($usage);
      if (!empty($usage['accessible']) && !empty($usage['url'])) {
        $items[] = Link::fromTextAndUrl($text, $usage['url'])->toRenderable();
      }
      else {
        $items[] = ['#markup' => $text];
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['media-library-image-crop-usage-warning__usage-list']],
    ];
  }

  /**
   * @param array<string, mixed> $usage
   */
  private function formatUsageLabel(array $usage): string {
    $text = (string) $this->t('@type: @title', [
      '@type' => $usage['type_label'],
      '@title' => $usage['label'],
    ]);

    if (!empty($usage['via_label'])) {
      $text .= ' ' . (string) $this->t('(über @paragraph_type)', [
        '@paragraph_type' => $usage['via_label'],
      ]);
    }

    return $text;
  }

}
