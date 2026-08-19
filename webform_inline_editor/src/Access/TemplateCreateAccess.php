<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformInterface;

/**
 * Access check for creating a webform node from a template.
 */
final class TemplateCreateAccess implements AccessInterface, ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function access(WebformInterface $webform, AccountInterface $account): AccessResultInterface {
    if (!$webform->isTemplate()) {
      return AccessResult::forbidden('Webform is not a template.')->addCacheableDependency($webform);
    }

    $node_access = $this->entityTypeManager->getAccessControlHandler('node')
      ->createAccess('webform', $account, [], TRUE);

    return AccessResult::allowedIf($webform->access('duplicate', $account))
      ->andIf($node_access)
      ->addCacheableDependency($webform)
      ->cachePerPermissions();
  }

}
