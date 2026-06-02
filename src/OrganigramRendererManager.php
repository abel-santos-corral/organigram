<?php

namespace Drupal\organigram;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages OrganigramRenderer plugins.
 *
 * Discovers plugins annotated with \Drupal\organigram\Annotation\OrganigramRenderer
 * from the Plugin\OrganigramRenderer namespace of every enabled module.
 *
 * Modules providing a renderer should place their plugin class at:
 *   src/Plugin/OrganigramRenderer/MyRenderer.php
 *
 * Example annotation:
 * @code
 * @OrganigramRenderer(
 *   id = "my_renderer",
 *   label = @Translation("My Renderer"),
 *   description = @Translation("Renders the organigram using MyRenderer."),
 * )
 * @endcode
 */
class OrganigramRendererManager extends DefaultPluginManager {

  /**
   * Constructs an OrganigramRendererManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/OrganigramRenderer',
      $namespaces,
      $module_handler,
      'Drupal\organigram\OrganigramRendererInterface',
      'Drupal\organigram\Annotation\OrganigramRenderer',
    );

    $this->alterInfo('organigram_renderer_info');
    $this->setCacheBackend($cache_backend, 'organigram_renderer_plugins');
  }

}
