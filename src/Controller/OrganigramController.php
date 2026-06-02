<?php

namespace Drupal\organigram\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\organigram\OrganigramRendererManager;
use Drupal\organigram\Service\OrganigramGraphBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles the organigram display page and JSON data endpoint.
 *
 * The display() method delegates rendering entirely to the active
 * OrganigramRenderer plugin, selected in /admin/config/organigram.
 *
 * The data() method returns the raw graph contract as a CacheableJsonResponse
 * so that reverse proxies and Drupal's page cache can store and invalidate
 * the JSON payload independently of the HTML page.
 */
class OrganigramController extends ControllerBase {

  /**
   * The organigram graph builder.
   */
  protected OrganigramGraphBuilder $graphBuilder;

  /**
   * The organigram renderer plugin manager.
   */
  protected OrganigramRendererManager $rendererManager;

  /**
   * The organigram config factory.
   *
   * Named rendererConfig to avoid collision with the
   * $configFactory property declared in ControllerBase.
   */
  protected ConfigFactoryInterface $rendererConfig;

  /**
   * Constructs an OrganigramController object.
   *
   * @param \Drupal\organigram\Service\OrganigramGraphBuilder $graph_builder
   *   The organigram graph builder.
   * @param \Drupal\organigram\OrganigramRendererManager $renderer_manager
   *   The organigram renderer plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    OrganigramGraphBuilder $graph_builder,
    OrganigramRendererManager $renderer_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->graphBuilder = $graph_builder;
    $this->rendererManager = $renderer_manager;
    $this->rendererConfig = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('organigram.graph_builder'),
      $container->get('plugin.manager.organigram_renderer'),
      $container->get('config.factory'),
    );
  }

  /**
   * Displays the organigram for a root node.
   *
   * Delegates the render array entirely to the active OrganigramRenderer
   * plugin.  The plugin is responsible for attaching its own library,
   * passing drupalSettings, and populating #cache.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The root organigram node.
   *
   * @return array
   *   A render array.
   */
  public function display(NodeInterface $node): array {
    $graph = $this->graphBuilder->build($node);
    $renderer = $this->getActiveRenderer();

    return $renderer->render($node, $graph);
  }

  /**
   * Returns the page title for an organigram display.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The root organigram node.
   *
   * @return string
   *   The page title.
   */
  public function title(NodeInterface $node): string {
    return $node->getTitle();
  }

  /**
   * Returns the organigram graph as a cacheable JSON response.
   *
   * The response carries cache tags for every organigram_node and
   * organigram_node_type entity in the tree so that Drupal's Dynamic Page
   * Cache and reverse proxies invalidate it precisely when any of those
   * entities is updated or deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The root organigram node.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The JSON response.
   */
  public function data(NodeInterface $node): CacheableJsonResponse {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($node);
    $cacheability->addCacheTags([
      'node_list:organigram_node',
      'config:organigram.organigram_node_type_list',
    ]);

    $graph = $this->graphBuilder->build($node);

    $response = new CacheableJsonResponse($graph);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Returns the active renderer plugin instance.
   *
   * Falls back to the first available renderer if the configured one is
   * not found, so the site does not crash when a renderer module is
   * uninstalled without updating the settings.
   *
   * @return \Drupal\organigram\OrganigramRendererInterface
   *   The active renderer plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   When no renderer plugins are available at all.
   */
  protected function getActiveRenderer() {
    $active = $this->rendererConfig
      ->get('organigram.settings')
      ->get('active_renderer');

    $definitions = $this->rendererManager->getDefinitions();

    // Fall back to the first available renderer if the stored one is gone.
    if (empty($active) || !isset($definitions[$active])) {
      $active = array_key_first($definitions);
    }

    return $this->rendererManager->createInstance($active);
  }

}
