<?php

namespace Drupal\organigram\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the OrganigramRenderer plugin annotation.
 *
 * Plugin namespace: Plugin\OrganigramRenderer
 *
 * @Annotation
 */
class OrganigramRenderer extends Plugin {

  /**
   * The plugin ID.
   */
  public string $pluginId;

  /**
   * The human-readable label.
   *
   * @ingroup plugin_translatable
   */
  public string $label;

  /**
   * A short description of the renderer.
   *
   * @ingroup plugin_translatable
   */
  public string $description = '';

}
