<?php

namespace Drupal\organigram;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\node\NodeInterface;

/**
 * Defines the interface for OrganigramRenderer plugins.
 *
 * A renderer plugin is responsible for turning a root NodeInterface and
 * the normalised graph contract produced by OrganigramGraphBuilder into a
 * Drupal render array.  It owns its own library declarations, template
 * requirements, and any renderer-specific drupalSettings it needs to pass
 * to its JavaScript layer.
 *
 * Implementations must be annotated with \Drupal\organigram\Annotation\OrganigramRenderer
 * and placed in the Plugin\OrganigramRenderer namespace of any module.
 */
interface OrganigramRendererInterface extends PluginInspectionInterface {

  /**
   * Builds the render array for the organigram.
   *
   * The returned array must include a populated #cache key so that
   * Drupal's page cache and Dynamic Page Cache can store and invalidate
   * the output correctly.
   *
   * @param \Drupal\node\NodeInterface $root
   *   The root organigram node.
   * @param array $graph
   *   The normalised graph contract produced by OrganigramGraphBuilder.
   * @param array $settings
   *   Optional renderer-specific settings, e.g. passed from a block
   *   instance configuration.
   *
   * @return array
   *   A render array with #cache already populated.
   */
  public function render(
    NodeInterface $root,
    array $graph,
    array $settings = [],
  ): array;

  /**
   * Returns the human-readable label of the renderer.
   *
   * @return string
   *   The renderer label.
   */
  public function label(): string;

}
