<?php

namespace Drupal\organigram\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\organigram\Entity\OrganigramNodeTypeInterface;

/**
 * Builds normalized graph contracts for organigram renderers.
 */
class OrganigramGraphBuilder {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityManager;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs an OrganigramGraphBuilder object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_manager,
    FileUrlGeneratorInterface $file_url_generator,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->entityManager = $entity_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Builds a normalized graph contract from the root node.
   *
   * @param \Drupal\node\NodeInterface $root
   *   The root organigram node.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   Optional cacheability metadata object to populate. When provided, all
   *   cache tags and contexts collected during the walk are merged into it.
   *
   * @return array
   *   The normalized graph contract.
   */
  public function build(
    NodeInterface $root,
    ?CacheableMetadata $cacheability = NULL,
  ): array {
    $graph = [
      'meta' => [
        'root' => (int) $root->id(),
        'version' => '1.0',
      ],
      'graph' => [
        'nodes' => [],
        'edges' => [],
      ],
      'visuals' => [],
    ];

    // Collect cacheability for every entity visited during the walk.
    $local_cache = new CacheableMetadata();

    // The root node itself contributes its cache tags.
    $local_cache->addCacheableDependency($root);

    // All organigram_node_type config entities affect the output.
    $local_cache->addCacheTags(['config:organigram.organigram_node_type_list']);

    $this->walkNode($root, $graph, NULL, 0, $local_cache);

    $context = [
      'root_node' => $root,
    ];

    $this->moduleHandler->alter(
      'organigram_graph',
      $graph,
      $context
    );

    if (empty($graph['visuals'])) {
      \Drupal::logger('organigram')->warning(
        'No visuals generated for organigram @nid.',
        ['@nid' => $root->id()]
      );
    }

    // Merge collected metadata into the caller-supplied object if given.
    if ($cacheability !== NULL) {
      $cacheability->addCacheableDependency($local_cache);
    }

    return $graph;
  }

  /**
   * Walks recursively through the organigram hierarchy.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node.
   * @param array $graph
   *   The graph contract.
   * @param \Drupal\node\NodeInterface|null $parent
   *   The parent node.
   * @param int $depth
   *   The current recursion depth.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata collector.
   */
  protected function walkNode(
    NodeInterface $node,
    array &$graph,
    ?NodeInterface $parent,
    int $depth,
    CacheableMetadata $cacheability,
  ): void {

    if ($depth > 15) {
      return;
    }

    $is_vacant = $this->fieldBool($node, 'field_is_vacant');

    $node_type_id = NULL;
    $node_type = NULL;

    if (
      $node->hasField('field_organigram_node_type') &&
      !$node->get('field_organigram_node_type')->isEmpty()
    ) {
      $node_type = $node->get('field_organigram_node_type')->entity;

      if ($node_type instanceof OrganigramNodeTypeInterface) {
        $node_type_id = $node_type->id();

        // Add the specific node-type config entity cache tags.
        $cacheability->addCacheableDependency($node_type);

        $this->ensureVisualDefinition(
          $graph,
          $node_type,
          $node_type_id,
        );
      }
    }

    $graph['graph']['nodes'][$node->id()] = $this->buildGraphNode(
      $node,
      $node_type_id,
      $is_vacant,
    );

    if ($parent) {
      $graph['graph']['edges'][] = $this->buildEdge(
        $parent,
        $node,
      );
    }

    foreach ($this->loadChildren($node) as $child) {
      // Each child node contributes its own cache tags.
      $cacheability->addCacheableDependency($child);
      $this->walkNode($child, $graph, $node, $depth + 1, $cacheability);
    }
  }

  /**
   * Ensures the visual definition exists in the graph.
   *
   * @param array $graph
   *   The graph contract.
   * @param \Drupal\organigram\Entity\OrganigramNodeTypeInterface $node_type
   *   The node type entity.
   * @param string $node_type_id
   *   The node type identifier.
   */
  protected function ensureVisualDefinition(
    array &$graph,
    OrganigramNodeTypeInterface $node_type,
    string $node_type_id,
  ): void {

    if (isset($graph['visuals'][$node_type_id])) {
      return;
    }

    $graph['visuals'][$node_type_id] = [
      'id' => $node_type->id(),
      'label' => $node_type->label(),

      'shape' => 'rounded_box',

      'palette' => [
        'background' => $node_type->getBoxBackground(),
        'foreground' => $node_type->getBoxFontColor(),
        'border' => $node_type->getLineColor(),
      ],

      'border' => [
        'width' => $node_type->getLineSize(),
        'style' => $node_type->getLineType(),
      ],

      'font' => [
        'size' => $node_type->getBoxFontSize(),
      ],
    ];
  }

  /**
   * Builds a graph node definition.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param string|null $node_type_id
   *   The node type identifier.
   * @param bool $is_vacant
   *   Whether the node is vacant.
   *
   * @return array
   *   The graph node definition.
   */
  protected function buildGraphNode(
    NodeInterface $node,
    ?string $node_type_id,
    bool $is_vacant,
  ): array {

    // Determine enable_modal: default TRUE for backward compatibility when
    // the field does not yet exist on a node (e.g. nodes created before the
    // field was added via organigram_update_9001).
    $enable_modal = !$node->hasField('field_enable_modal') || $node->get('field_enable_modal')->isEmpty()
      ? TRUE
      : (bool) $node->get('field_enable_modal')->value;

    $graph_node = [
      'id' => (int) $node->id(),
      'title' => $node->getTitle(),
      'type' => $node_type_id,

      'data' => [
        'is_hidden' => $this->fieldBool($node, 'field_is_hidden'),
        'display_weight' => (int) ($this->fieldString($node, 'field_display_weight') ?? 0),
        'collapsed' => $this->fieldBool($node, 'field_collapsed_default'),
        'enable_modal' => $enable_modal,

        'position_title' => $this->fieldString($node, 'field_position_title'),

        'vacant' => $is_vacant,
      ],
    ];

    // Modal-dependent fields are only included when modal is enabled.
    // This keeps the JSON payload lean and avoids leaking data when the
    // modal is intentionally disabled.
    if ($enable_modal) {
      $graph_node['data'] += [
        'field_scope_of_works_title' => $this->fieldString(
          $node,
          'field_scope_of_works_title'
        ),

        'field_scope_of_work' => $this->fieldProcessedText(
          $node,
          'field_scope_of_work'
        ),

        'start_date' => $this->fieldString($node, 'field_start_date'),
        'end_date' => $this->fieldString($node, 'field_end_date'),
        'relation_type' => $this->fieldString($node, 'field_relation_type'),
        'related_nodes' => $this->buildRelatedNodes($node),
      ];

      if (!$is_vacant) {
        $graph_node['data'] += [
          'responsible_name'    => $this->fieldString($node, 'field_responsible_name'),
          'responsible_photo'   => $this->fieldImageUrl($node, 'field_responsible_photo'),
          'cv'                  => $this->fieldFileUrl($node, 'field_cv_document'),
          'declaration_interest' => $this->fieldFileUrl($node, 'field_declaration_interest'),
        ];
      }
    }

    return $graph_node;
  }

  /**
   * Builds a graph edge definition.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node.
   * @param \Drupal\node\NodeInterface $child
   *   The child node.
   *
   * @return array
   *   The graph edge definition.
   */
  protected function buildEdge(
    NodeInterface $parent,
    NodeInterface $child,
  ): array {
    return [
      'id' => $parent->id() . '-' . $child->id(),
      'source' => (int) $parent->id(),
      'target' => (int) $child->id(),
      'type' => 'hierarchical',
    ];
  }

  /**
   * Loads child organigram nodes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The parent node.
   *
   * @return array
   *   The child nodes.
   */
  protected function loadChildren(NodeInterface $node): array {
    $storage = $this->entityManager->getStorage('node');

    $child_ids = $storage->getQuery()
      ->condition('type', 'organigram_node')
      ->condition('field_parent_node', $node->id())
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_is_hidden', 0)
      ->sort('field_display_weight', 'ASC')
      ->sort('title', 'ASC')
      ->accessCheck(TRUE)
      ->execute();

    if (empty($child_ids)) {
      return [];
    }

    return array_filter(
      $storage->loadMultiple($child_ids),
      fn ($entity) => $entity instanceof NodeInterface
    );
  }

  /**
   * Builds related node references.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   *
   * @return array
   *   The related node data.
   */
  protected function buildRelatedNodes(NodeInterface $node): array {
    if (
      !$node->hasField('field_related_nodes') ||
      $node->get('field_related_nodes')->isEmpty()
    ) {
      return [];
    }

    $out = [];

    foreach ($node->get('field_related_nodes') as $ref) {
      $related = $ref->entity;

      if (!$related instanceof NodeInterface) {
        continue;
      }

      $out[] = [
        'id' => (int) $related->id(),
        'title' => $related->getTitle(),
      ];
    }

    return $out;
  }

  /**
   * Returns a string field value.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $field_name
   *   The field machine name.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The field value.
   */
  protected function fieldString(
    NodeInterface $node,
    string $field_name,
    mixed $default = NULL,
  ): mixed {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return $default;
    }

    return $node->get($field_name)->value ?? $default;
  }

  /**
   * Returns a boolean field value.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $field_name
   *   The field machine name.
   *
   * @return bool
   *   The field value.
   */
  protected function fieldBool(
    NodeInterface $node,
    string $field_name,
  ): bool {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return FALSE;
    }

    return (bool) $node->get($field_name)->value;
  }

  /**
   * Returns processed text field markup.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $field_name
   *   The field machine name.
   *
   * @return string|null
   *   The processed markup.
   */
  protected function fieldProcessedText(
    NodeInterface $node,
    string $field_name,
  ): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    return (string) $node->get($field_name)
      ->first()
      ->get('processed')
      ->getValue();
  }

  /**
   * Returns an absolute file URL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $field_name
   *   The field machine name.
   *
   * @return string|null
   *   The file URL.
   */
  protected function fieldFileUrl(
    NodeInterface $node,
    string $field_name,
  ): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $file = $node->get($field_name)->entity;

    return $file instanceof FileInterface
      ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri())
      : NULL;
  }

  /**
   * Returns an absolute image URL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $field_name
   *   The field machine name.
   *
   * @return string|null
   *   The image URL.
   */
  protected function fieldImageUrl(
    NodeInterface $node,
    string $field_name,
  ): ?string {
    return $this->fieldFileUrl($node, $field_name);
  }

}
