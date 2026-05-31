<?php

namespace Drupal\organigram\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\organigram\Service\OrganigramGraphBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles the organigram display page and JSON data endpoint.
 *
 * The /organigram/{nid}/data endpoint returns the complete hierarchical tree.
 * Each node includes a `organigram_node_type_settings` object with visual
 * properties defined in the Organigram Node Type config entity:
 *
 * @code
 * "organigram_node_type_settings": {
 *   "id": "department",
 *   "label": "Department",
 *   "box_font_size": 11,
 *   "box_font_color": "#ffffff",
 *   "box_background": "#0055AA",
 *   "line_size": "2",
 *   "line_color": "#0055AA",
 *   "line_type": "solid",
 *   "line_dash_array": "none"
 * }
 * @endcode
 */
class OrganigramController extends ControllerBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityManager;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The Organigram graph builder.
   */
  protected OrganigramGraphBuilder $graphBuilder;

  /**
   * Constructs an OrganigramController object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator,
    OrganigramGraphBuilder $graph_builder
  ) {
    $this->entityManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->graphBuilder = $graph_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $entity_type_manager = $container->get('entity_type.manager');
    $file_url_generator = $container->get('file_url_generator');
    $graph_builder = $container->get('organigram.graph_builder');
    assert($entity_type_manager instanceof EntityTypeManagerInterface);
    assert($file_url_generator instanceof FileUrlGeneratorInterface);

    return new static(
      $entity_type_manager,
      $file_url_generator,
      $graph_builder
    );
  }

  /**
   * Displays the organigram for a root node.
   *
   * Cache tags are derived from the root node and propagated to the render
   * array so that Drupal's page cache invalidates correctly whenever any
   * organigram_node or organigram_node_type entity is saved or deleted.
   */
  public function display(NodeInterface $node): array {
    $dataUrl = Url::fromRoute(
      'organigram.data',
      ['node' => $node->id()],
      ['absolute' => TRUE]
    )->toString();

    return [
      '#theme' => 'organigram_display',
      '#root_node_id' => $node->id(),
      '#settings' => [],
      '#attached' => [
        'library' => ['organigram/organigram'],
        'drupalSettings' => [
          'organigram' => [
            'dataUrl' => $dataUrl,
            'rootId' => (int) $node->id(),
            'legendTitle' => (string) $this->t('Legend'),
          ],
        ],
      ],
      '#cache' => [
        'tags' => Cache::mergeTags(
          $node->getCacheTags(),
          ['node_list:organigram_node'],
          ['config:organigram.organigram_node_type_list'],
        ),
      ],
    ];
  }

  /**
   * Returns the page title for an organigram display.
   */
  public function title(NodeInterface $node): string {
    return $node->getTitle();
  }

  /**
   * Returns the organigram data as a cacheable JSON response.
   *
   * The response carries the cache tags of every organigram_node and
   * organigram_node_type entity that contributed to the payload, so Drupal's
   * Dynamic Page Cache and reverse proxies (Varnish, CDN) can invalidate it
   * precisely when any of those entities is updated or deleted.
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

}
