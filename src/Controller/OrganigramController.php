<?php

namespace Drupal\organigram\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\organigram\Entity\OrganigramNodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the organigram display page and JSON data endpoint.
 *
 * The /organigram/{nid}/data endpoint returns the complete hierarchical tree.
 * Each node includes a `organigram_node_type_settings` object with visual properties
 * defined in the Organigram Node Type config entity:
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

  protected EntityTypeManagerInterface $organigramEntityTypeManager;
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    $this->organigramEntityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator  = $file_url_generator;
  }

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

  // ---------------------------------------------------------------------------
  // Routes
  // ---------------------------------------------------------------------------

  public function display(NodeInterface $node): array {
    $dataUrl = Url::fromRoute('organigram.data', ['node' => $node->id()], ['absolute' => TRUE])->toString();
    \Drupal::messenger()->addStatus(t('ITSSSSS : @url', ['@url' => $dataUrl]));
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
        'tags' => $node->getCacheTags(), // Invalidates this cache if the node is edited
      ],
    ];
  }

  public function title(NodeInterface $node): string {
    return $node->getTitle();
  }

  public function data(NodeInterface $node, Request $request): JsonResponse {
    return new JsonResponse($this->buildNodeData($node, 0));
  }

  // ---------------------------------------------------------------------------
  // Tree builder
  // ---------------------------------------------------------------------------

  protected function buildNodeData(NodeInterface $node, int $depth): ?array {
    if ($depth > 15) {
      return NULL;
    }

    $is_vacant = $this->fieldBool($node, 'field_is_vacant');

    $data = [
      'id'     => (int) $node->id(),
      'title'  => $node->getTitle(),
      'is_hidden' => $this->fieldBool($node, 'field_is_hidden', FALSE),

      // Organigram Node Type replaces the old hardcoded list — includes full visual spec.
      'organigram_node_type'          => NULL,
      'organigram_node_type_settings' => NULL,

      'display_weight' => (int) ($this->fieldString($node, 'field_display_weight') ?? 0),
      'collapsed'      => $this->fieldBool($node, 'field_collapsed_default'),

      'position_title'      => $this->fieldString($node, 'field_position_title'),
      'vacant'              => $is_vacant,

      'field_scope_of_works_title' => $this->fieldString($node, 'field_scope_of_works_title'),
      'field_scope_of_work' => $this->fieldProcessedText($node, 'field_scope_of_work'),
      'start_date'    => $this->fieldString($node, 'field_start_date'),
      'end_date'      => $this->fieldString($node, 'field_end_date'),
      'relation_type' => $this->fieldString($node, 'field_relation_type'),
      'related_nodes' => $this->buildRelatedNodes($node),
    ];

    // ── Resolve OrganigramNodeType config entity ───────────────────────────────────────
    if ($node->hasField('field_organigram_node_type') && !$node->get('field_organigram_node_type')->isEmpty()) {
      /** @var \Drupal\organigram\Entity\OrganigramNodeTypeInterface|null $gt */
      $gt = $node->get('field_organigram_node_type')->entity;
      if ($gt instanceof OrganigramNodeTypeInterface) {
        $data['organigram_node_type'] = $gt->id();
        $data['organigram_node_type_settings'] = [
          'id'             => $gt->id(),
          'label'          => $gt->label(),
          'box_font_size'  => $gt->getBoxFontSize(),
          'box_font_color' => $gt->getBoxFontColor(),
          'box_background' => $gt->getBoxBackground(),
          'line_size'      => $gt->getLineSize(),
          'line_color'     => $gt->getLineColor(),
          'line_type'      => $gt->getLineType(),
          'line_dash_array'=> $gt->getLineDashArray(),
        ];
      }
    }

    // ── Responsible person (hidden when Vacant) ───────────────────────────────
    if (!$is_vacant) {
      $data['responsible_name']     = $this->fieldString($node, 'field_responsible_name');
      $data['responsible_photo']    = $this->fieldImageUrl($node, 'field_responsible_photo');
      $data['cv']                   = $this->fieldFileUrl($node, 'field_cv_document');
      $data['declaration_interest'] = $this->fieldFileUrl($node, 'field_declaration_interest');
    }

    $data['children'] = $this->buildChildren($node, $depth);
    return $data;
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  protected function buildChildren(NodeInterface $node, int $depth): array {
    $storage = $this->organigramEntityTypeManager->getStorage('node');
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

  protected function buildRelatedNodes(NodeInterface $node): array {
    if (!$node->hasField('field_related_nodes') || $node->get('field_related_nodes')->isEmpty()) {
      return [];
    }
    $out = [];
    foreach ($node->get('field_related_nodes') as $ref) {
      $related = $ref->entity;
      if (!$related instanceof NodeInterface) {
        continue;
      }
      $gt = NULL;
      if ($related->hasField('field_organigram_node_type') && !$related->get('field_organigram_node_type')->isEmpty()) {
        $gt_entity = $related->get('field_organigram_node_type')->entity;
        $gt = $gt_entity instanceof OrganigramNodeTypeInterface ? $gt_entity->id() : NULL;
      }
      $out[] = ['id' => (int) $related->id(), 'title' => $related->getTitle(), 'organigram_node_type' => $gt];
    }
    return $out;
  }

  protected function fieldString(NodeInterface $node, string $field_name, mixed $default = NULL): mixed {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) return $default;
    return $node->get($field_name)->value ?? $default;
  }

  protected function fieldBool(NodeInterface $node, string $field_name, bool $default = FALSE): bool {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) return $default;
    return (bool) $node->get($field_name)->value;
  }

  protected function fieldProcessedText(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) return NULL;
    return (string) $node->get($field_name)->first()->get('processed')->getValue();
  }

  protected function fieldFileUrl(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) return NULL;
    $file = $node->get($field_name)->entity;
    return $file instanceof FileInterface ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()) : NULL;
  }

  protected function fieldImageUrl(NodeInterface $node, string $field_name): ?string {
    return $this->fieldFileUrl($node, $field_name);
  }

  protected function fieldLink(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) return NULL;
    return $node->get($field_name)->uri ?? NULL;
  }

}
