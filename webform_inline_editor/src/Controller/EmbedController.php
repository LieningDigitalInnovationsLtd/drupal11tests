<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\webform\Utility\WebformDialogHelper;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embeddable webform edit form.
 */
final class EmbedController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
    );
  }

  public function build(WebformInterface $webform): array {
    $form_object = $this->entityTypeManager->getFormObject('webform', 'edit');
    $form_object->setEntity($webform);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['webform-inline-editor-embed__content']],
      'form' => $this->formBuilder->getForm($form_object),
      '#attached' => [
        'library' => ['webform_inline_editor/embed'],
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
