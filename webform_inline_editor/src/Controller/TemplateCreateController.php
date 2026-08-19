<?php

declare(strict_types=1);

namespace Drupal\webform_inline_editor\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform_inline_editor\Service\TemplateNodeCreator;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Creates a webform node from a template without a duplicate dialog.
 */
final class TemplateCreateController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly TemplateNodeCreator $templateNodeCreator,
    private readonly MessengerInterface $messenger,
    private readonly TranslationInterface $stringTranslation,
  ) {}

  public function fromTemplate(WebformInterface $webform): RedirectResponse {
    $node = $this->templateNodeCreator->create($webform);
    $this->messenger->addStatus($this->stringTranslation->translate('Created %title from the selected template.', [
      '%title' => $node->label(),
    ]));
    return new RedirectResponse($node->toUrl('edit-form')->toString());
  }

}
