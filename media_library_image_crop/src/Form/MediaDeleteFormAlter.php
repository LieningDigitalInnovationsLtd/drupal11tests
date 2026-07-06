<?php

declare(strict_types=1);

namespace Drupal\media_library_image_crop\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\media\MediaInterface;
use Drupal\media_library_image_crop\MediaUsageFinder;
use Drupal\media_library_image_crop\MediaUsageWarningBuilder;

final class MediaDeleteFormAlter {

  use StringTranslationTrait;

  public function __construct(
    private readonly MediaUsageFinder $usageFinder,
    private readonly MediaUsageWarningBuilder $warningBuilder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PrivateTempStoreFactory $tempStoreFactory,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function alterSingleDeleteForm(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityDeleteForm) {
      return;
    }

    $media = $form_object->getEntity();
    if (!$media instanceof MediaInterface) {
      return;
    }

    $warning = $this->warningBuilder->buildSingleWarning($this->usageFinder->findUsages($media));
    if ($warning === []) {
      return;
    }

    $warning['#weight'] = -20;
    $form['usage_warning'] = $warning;

    if (isset($form['description'])) {
      $form['description'] = [
        '#markup' => '<p>' . $this->t('Diese Aktion kann nicht rückgängig gemacht werden. Referenzen auf dieses Medium können danach ungültig werden.') . '</p>',
        '#weight' => -10,
      ];
    }
  }

  public function alterBulkDeleteForm(array &$form, FormStateInterface $form_state): void {
    $selection = $this->tempStoreFactory
      ->get('entity_delete_multiple_confirm')
      ->get($this->currentUser->id() . ':media');

    if (empty($selection) || !is_array($selection)) {
      return;
    }

    $media_items = $this->entityTypeManager->getStorage('media')->loadMultiple(array_keys($selection));
    if ($media_items === []) {
      return;
    }

    $usages_by_media = $this->usageFinder->findUsagesMultiple($media_items);
    $media_labels = [];
    foreach ($media_items as $media) {
      $media_labels[(int) $media->id()] = $media->label() ?? (string) $media->id();
    }

    $warning = $this->warningBuilder->buildBulkWarning($usages_by_media, $media_labels);
    if ($warning === []) {
      return;
    }

    $warning['#weight'] = -20;
    $form['usage_warning'] = $warning;

    if (isset($form['description'])) {
      $form['description'] = [
        '#markup' => '<p>' . $this->t('Diese Aktion kann nicht rückgängig gemacht werden. Referenzen auf die betroffenen Medien können danach ungültig werden.') . '</p>',
        '#weight' => -10,
      ];
    }
  }

}
