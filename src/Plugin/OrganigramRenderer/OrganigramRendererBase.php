<?php

namespace Drupal\organigram\Plugin\OrganigramRenderer;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\organigram\OrganigramRendererInterface;
use Drupal\node\NodeInterface;

/**
 * Base class for OrganigramRenderer plugins.
 *
 * Subclasses must implement render().  The label() method is provided here
 * and reads from the plugin annotation so subclasses do not need to repeat
 * it.
 */
abstract class OrganigramRendererBase extends PluginBase implements OrganigramRendererInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    $definition = $this->getPluginDefinition();
    return (string) ($definition['label'] ?? $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  abstract public function render(
    NodeInterface $root,
    array $graph,
    array $settings = [],
  ): array;

}
