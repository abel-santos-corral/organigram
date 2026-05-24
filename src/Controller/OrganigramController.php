<?php

namespace Drupal\organigram\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\organigram\Entity\OrganigramNodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

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
   * Constructs an OrganigramController object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    $this->entityManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $entity_type_manager = $container->get('entity_type.manager');
    $file_url_generator = $container->get('file_url_generator');
    assert($entity_type_manager instanceof EntityTypeManagerInterface);
    assert($file_url_generator instanceof FileUrlGeneratorInterface);

    return new static(
      $entity_type_manager,
      $file_url_generator,
    );
  }

  /**
   * Displays the organigram for a root node.
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
          ],
        ],
      ],
      '#cache' => [
        'tags' => $node->getCacheTags(),
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
   * Returns the organigram data as JSON.
   */
  public function data(NodeInterface $node): JsonResponse {
    return new JsonResponse($this->buildNodeData($node, 0));
  }

  /**
   * Builds render data for one organigram node and its descendants.
   */
  protected function buildNodeData(NodeInterface $node, int $depth): ?array {
    if ($depth > 15) {
      return NULL;
    }

    $is_vacant = $this->fieldBool($node, 'field_is_vacant');

    $data = [
      'id'     => (int) $node->id(),
      'title'  => $node->getTitle(),
      'is_hidden' => $this->fieldBool($node, 'field_is_hidden'),

      'organigram_node_type'          => NULL,
      'organigram_node_type_settings' => NULL,

      'display_weight' => (int) ($this->fieldString($node, 'field_display_weight') ?? 0),
      'collapsed'      => $this->fieldBool($node, 'field_collapsed_default'),

      'position_title'      => $this->fieldString($node, 'field_position_title'),
      'vacant'              => $is_vacant,

      'field_scope_of_works_title' => $this->fieldString(
        $node,
        'field_scope_of_works_title'
      ),
      'field_scope_of_work' => $this->fieldProcessedText(
        $node,
        'field_scope_of_work'
      ),
      'start_date'    => $this->fieldString($node, 'field_start_date'),
      'end_date'      => $this->fieldString($node, 'field_end_date'),
      'relation_type' => $this->fieldString($node, 'field_relation_type'),
      'related_nodes' => $this->buildRelatedNodes($node),
    ];

    if (
      $node->hasField('field_organigram_node_type') &&
      !$node->get('field_organigram_node_type')->isEmpty()
    ) {
      /** @var \Drupal\organigram\Entity\OrganigramNodeTypeInterface|null $node_type */
      $node_type = $node->get('field_organigram_node_type')->entity;
      if ($node_type instanceof OrganigramNodeTypeInterface) {
        $data['organigram_node_type'] = $node_type->id();
        $data['organigram_node_type_settings'] = [
          'id'             => $node_type->id(),
          'label'          => $node_type->label(),
          'box_font_size'  => $node_type->getBoxFontSize(),
          'box_font_color' => $node_type->getBoxFontColor(),
          'box_background' => $node_type->getBoxBackground(),
          'line_size'      => $node_type->getLineSize(),
          'line_color'     => $node_type->getLineColor(),
          'line_type'      => $node_type->getLineType(),
          'line_dash_array' => $node_type->getLineDashArray(),
        ];
      }
    }

    if (!$is_vacant) {
      $data['responsible_name']     = $this->fieldString($node, 'field_responsible_name');
      $data['responsible_photo']    = $this->fieldImageUrl($node, 'field_responsible_photo');
      $data['cv']                   = $this->fieldFileUrl($node, 'field_cv_document');
      $data['declaration_interest'] = $this->fieldFileUrl($node, 'field_declaration_interest');
    }

    $data['children'] = $this->buildChildren($node, $depth);
    return $data;
  }

  /**
   * Builds child node data for a parent node.
   */
  protected function buildChildren(NodeInterface $node, int $depth): array {
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

    $children = [];
    foreach ($storage->loadMultiple($child_ids) as $child) {
      if (!$child instanceof NodeInterface) {
        continue;
      }
      $child_data = $this->buildNodeData($child, $depth + 1);
      if ($child_data !== NULL) {
        $children[] = $child_data;
      }
    }
    return $children;
  }

  /**
   * Builds related node metadata.
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
      $node_type_id = NULL;
      if (
        $related->hasField('field_organigram_node_type') &&
        !$related->get('field_organigram_node_type')->isEmpty()
      ) {
        $gt_entity = $related->get('field_organigram_node_type')->entity;
        $node_type_id = $gt_entity instanceof OrganigramNodeTypeInterface
          ? $gt_entity->id()
          : NULL;
      }
      $out[] = [
        'id' => (int) $related->id(),
        'title' => $related->getTitle(),
        'organigram_node_type' => $node_type_id,
      ];
    }
    return $out;
  }

  /**
   * Returns a scalar field value.
   */
  protected function fieldString(NodeInterface $node, string $field_name, mixed $default = NULL): mixed {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return $default;
    }
    return $node->get($field_name)->value ?? $default;
  }

  /**
   * Returns a boolean field value.
   */
  protected function fieldBool(NodeInterface $node, string $field_name): bool {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return FALSE;
    }
    return (bool) $node->get($field_name)->value;
  }

  /**
   * Returns a processed text field value.
   */
  protected function fieldProcessedText(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    return (string) $node->get($field_name)->first()->get('processed')->getValue();
  }

  /**
   * Returns a file field absolute URL.
   */
  protected function fieldFileUrl(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    $file = $node->get($field_name)->entity;
    return $file instanceof FileInterface
      ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri())
      : NULL;
  }

  /**
   * Returns an image field absolute URL.
   */
  protected function fieldImageUrl(NodeInterface $node, string $field_name): ?string {
    return $this->fieldFileUrl($node, $field_name);
  }

  /**
   * Returns a link field URI.
   */
  protected function fieldLink(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    return $node->get($field_name)->uri ?? NULL;
  }

}
