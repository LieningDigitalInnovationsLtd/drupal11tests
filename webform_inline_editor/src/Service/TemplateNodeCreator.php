<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\webform\WebformInterface;

/**
 * Creates a webform and linked webform node from a template.
 */
final class TemplateNodeCreator {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function create(WebformInterface $template): NodeInterface {
    if (!$template->isTemplate()) {
      throw new \InvalidArgumentException('Webform is not a template.');
    }

    $title = $template->label();
    $webform = $template->createDuplicate();
    $webform->set('title', $title);
    $webform->set('id', $this->uniqueId($title));
    $webform->set('template', FALSE);
    $webform->set('description', '');
    $webform->setOwnerId((int) $this->currentUser->id());
    $webform->save();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'webform',
      'title' => $title,
      'uid' => (int) $this->currentUser->id(),
      'status' => NodeInterface::NOT_PUBLISHED,
      'webform' => [
        'target_id' => $webform->id(),
        'default_data' => '',
        'status' => 'open',
      ],
    ]);
    $node->save();

    return $node;
  }

  private function uniqueId(string $title): string {
    $storage = $this->entityTypeManager->getStorage('webform');
    $base = Html::getId(mb_strtolower($title));
    $base = preg_replace('/[^a-z0-9_]+/', '_', str_replace('-', '_', $base)) ?: 'webform';
    $base = trim($base, '_');
    $base = mb_substr($base, 0, 32) ?: 'webform';

    $id = $base;
    $suffix = 2;
    while ($storage->load($id)) {
      $id = mb_substr($base, 0, 28) . '_' . $suffix++;
    }

    return $id;
  }

}
