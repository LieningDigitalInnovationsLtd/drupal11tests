<?php

declare(strict_types=1);

namespace Drupal\media_library_image_crop;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\media\MediaInterface;

final class MediaUsageFinder {

  /**
   * @var array<string, array<string, array<string, mixed>>>
   */
  private array $mediaFieldMap = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly Connection $database,
  ) {}

  /**
   * @return array<int, array<string, mixed>>
   */
  public function findUsages(MediaInterface $media): array {
    $media_id = (int) $media->id();
    $usages = [];

    foreach (['entity_reference', 'entity_reference_revisions'] as $field_type) {
      foreach ($this->getMediaReferenceFields($field_type) as $entity_type_id => $fields) {
        foreach ($fields as $field_name => $field_info) {
          $entity_ids = $this->queryReferencingEntities($entity_type_id, $field_name, $media_id);
          foreach ($entity_ids as $entity_id) {
            $usage = $this->buildUsageRecord($entity_type_id, (int) $entity_id, $field_name);
            if ($usage !== NULL) {
              $usages[] = $usage;
            }
          }
        }
      }
    }

    return $this->dedupeUsages($usages);
  }

  /**
   * @param \Drupal\media\MediaInterface[] $media_items
   *
   * @return array<int, array<int, array<string, mixed>>>
   */
  public function findUsagesMultiple(array $media_items): array {
    $results = [];
    foreach ($media_items as $media) {
      if ($media instanceof MediaInterface) {
        $results[(int) $media->id()] = $this->findUsages($media);
      }
    }
    return $results;
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  private function getMediaReferenceFields(string $field_type): array {
    if (!isset($this->mediaFieldMap[$field_type])) {
      $map = [];
      foreach ($this->entityFieldManager->getFieldMapByFieldType($field_type) as $entity_type_id => $fields) {
        foreach ($fields as $field_name => $field_info) {
          $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
          $storage = $storage_definitions[$field_name] ?? NULL;
          if ($storage && $storage->getSetting('target_type') === 'media') {
            $map[$entity_type_id][$field_name] = $field_info;
          }
        }
      }
      $this->mediaFieldMap[$field_type] = $map;
    }

    return $this->mediaFieldMap[$field_type];
  }

  /**
   * @return int[]
   */
  private function queryReferencingEntities(string $entity_type_id, string $field_name, int $media_id): array {
    $table = $entity_type_id . '__' . $field_name;
    if (!$this->database->schema()->tableExists($table)) {
      return [];
    }

    $column = $field_name . '_target_id';
    if (!$this->database->schema()->fieldExists($table, $column)) {
      return [];
    }

    return array_map('intval', $this->database->select($table, 't')
      ->fields('t', ['entity_id'])
      ->condition($column, $media_id)
      ->distinct()
      ->execute()
      ->fetchCol());
  }

  private function buildUsageRecord(string $entity_type_id, int $entity_id, string $field_name): ?array {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity instanceof FieldableEntityInterface) {
      return NULL;
    }

    $display_entity = $entity;
    $via_label = NULL;

    if ($entity_type_id === 'paragraph' && method_exists($entity, 'getParentEntity')) {
      $parent = $entity->getParentEntity();
      if ($parent instanceof FieldableEntityInterface) {
        $display_entity = $parent;
        $via_label = $this->getBundleLabel($entity);
      }
    }

    $route = $display_entity->hasLinkTemplate('edit-form') ? 'edit-form' : 'canonical';
    $url = $display_entity->toUrl($route);

    return [
      'key' => $display_entity->getEntityTypeId() . ':' . $display_entity->id() . ':' . $field_name,
      'label' => $display_entity->label() ?? (string) $display_entity->id(),
      'type_label' => $this->getBundleLabel($display_entity),
      'via_label' => $via_label,
      'url' => $url,
      'accessible' => $url->access(),
    ];
  }

  private function getBundleLabel(FieldableEntityInterface $entity): string {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
    return $bundles[$bundle]['label'] ?? $bundle;
  }

  /**
   * @param array<int, array<string, mixed>> $usages
   *
   * @return array<int, array<string, mixed>>
   */
  private function dedupeUsages(array $usages): array {
    $deduped = [];
    $seen = [];
    foreach ($usages as $usage) {
      $key = $usage['key'];
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $deduped[] = $usage;
    }
    return $deduped;
  }

}
