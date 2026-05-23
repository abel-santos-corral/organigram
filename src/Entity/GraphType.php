<?php

namespace Drupal\organigram\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\organigram\Form\GraphTypeForm;
use Drupal\organigram\GraphTypeListBuilder;

/**
 * Defines the Graph Type config entity.
 *
 * @ConfigEntityType(
 *   id = "graph_type",
 *   label = @Translation("Graph Type"),
 *   label_collection = @Translation("Graph Types"),
 *   handlers = {
 *     "list_builder" = "Drupal\organigram\GraphTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\organigram\Form\GraphTypeForm",
 *       "edit" = "Drupal\organigram\Form\GraphTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   config_prefix = "graph_type",
 *   admin_permission = "administer organigram",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/graph-type/{graph_type}",
 *     "add-form" = "/admin/structure/graph-type/add",
 *     "edit-form" = "/admin/structure/graph-type/{graph_type}/edit",
 *     "delete-form" = "/admin/structure/graph-type/{graph_type}/delete",
 *     "collection" = "/admin/structure/graph-type"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "box_font_size",
 *     "box_font_color",
 *     "box_background",
 *     "line_size",
 *     "line_color",
 *     "line_type"
 *   }
 * )
 */
class GraphType extends ConfigEntityBase implements GraphTypeInterface {


  // ── Entity properties (persisted in config) ────────────────────────────────

  /**
   * Machine name, e.g. 'department'.
   */
  protected string $id;

  /**
   * Human-readable label, e.g. 'Department'.
   */
  protected string $label;

  /**
   * Box: font size in pixels (default 11).
   */
  protected int $box_font_size = 11;

  /**
   * Box: font colour hex (default #1a1a18).
   */
  protected string $box_font_color = '#1a1a18';

  /**
   * Box: background colour hex (default #ffffff).
   */
  protected string $box_background = '#ffffff';

  /**
   * Line: width as string — '0.5' | '1' | '2' (default '1').
   */
  protected string $line_size = '1';

  /**
   * Line: colour hex (default #cccccc).
   */
  protected string $line_color = '#cccccc';

  /**
   * Line: type — solid | dashed | dotted | dashdot (default 'solid').
   */
  protected string $line_type = 'solid';

  // ── GraphTypeInterface ─────────────────────────────────────────────────────

  /**
   * {@inheritdoc}
   */
  public function getBoxFontSize(): int {
    return $this->box_font_size;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoxFontColor(): string {
    return $this->box_font_color;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoxBackground(): string {
    return $this->box_background;
  }

  /**
   * {@inheritdoc}
   */
  public function getLineSize(): string {
    return $this->line_size;
  }

  /**
   * {@inheritdoc}
   */
  public function getLineColor(): string {
    return $this->line_color;
  }

  /**
   * {@inheritdoc}
   */
  public function getLineType(): string {
    return $this->line_type;
  }

  /**
   * {@inheritdoc}
   *
   * Converts the stored line_type into an SVG stroke-dasharray value.
   */
  public function getLineDashArray(): string {
    return match ($this->line_type) {
      'dashed'  => '8,4',
      'dotted'  => '2,3',
      'dashdot' => '8,3,2,3',
      default   => 'none',   // solid
    };
  }

  // ── Static helpers ─────────────────────────────────────────────────────────

  /**
   * Returns the allowed line size options (value => label).
   *
   * @return array<string, string>
   */
  public static function lineSizeOptions(): array {
    return [
      '0.5' => '0.5 px',
      '1'   => '1 px',
      '2'   => '2 px',
    ];
  }

  /**
   * Returns the allowed line type options (value => label).
   *
   * @return array<string, string>
   */
  public static function lineTypeOptions(): array {
    return [
      'solid'   => (string) new TranslatableMarkup('Solid'),
      'dashed'  => (string) new TranslatableMarkup('Dashed'),
      'dotted'  => (string) new TranslatableMarkup('Dotted'),
      'dashdot' => (string) new TranslatableMarkup('Dash-dot'),
    ];
  }

}
