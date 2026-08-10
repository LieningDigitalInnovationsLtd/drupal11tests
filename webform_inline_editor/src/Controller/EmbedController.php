<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\webform\Utility\WebformDialogHelper;
use Drupal\webform\WebformInterface;

/**
 * Embeddable webform edit form.
 */
final class EmbedController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly EntityFormBuilderInterface $entityFormBuilder,
  ) {}

  public function build(WebformInterface $webform): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['webform-inline-editor-embed__content']],
      'form' => $this->entityFormBuilder->getForm($webform, 'edit'),
      '#attached' => [
        'library' => ['webform_inline_editor/styles'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => $webform->getCacheTags(),
      ],
    ];
    WebformDialogHelper::attachLibraries($build);
    return $build;
  }

}
