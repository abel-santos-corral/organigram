<?php

namespace Drupal\organigram\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the Organigram Node Type config entity.
 *
 * @ConfigEntityType(
 *   id = "organigram_node_type",
 *   label = @Translation("Organigram Node Type"),
 *   label_collection = @Translation("Organigram Node Types"),
 *   handlers = {
 *     "list_builder" = "Drupal\organigram\OrganigramNodeTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\organigram\Form\OrganigramNodeTypeForm",
 *       "edit" = "Drupal\organigram\Form\OrganigramNodeTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   config_prefix = "organigram_node_type",
 *   admin_permission = "administer organigram",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/organigram-node-type/{organigram_node_type}",
 *     "add-form" = "/admin/structure/organigram-node-type/add",
 *     "edit-form" = "/admin/structure/organigram-node-type/{organigram_node_type}/edit",
 *     "delete-form" = "/admin/structure/organigram-node-type/{organigram_node_type}/delete",
 *     "collection" = "/admin/structure/organigram-node-type"
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
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class OrganigramNodeType extends ConfigEntityBase implements OrganigramNodeTypeInterface {

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
   * Line: width as string, for example '0.5', '1', or '2'.
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
      default => 'none',
    };
  }

  /**
   * Returns the allowed line size options (value => label).
   *
   * @return array<string, string>
   *   The allowed line size options.
   */
  public static function lineSizeOptions(): array {
    return [
      '0.5' => '0.5 px',
      '1'   => '1 px',
      '1.5' => '1.5 px',
      '2'   => '2 px',
    ];
  }

  /**
   * Returns the allowed line type options (value => label).
   *
   * @return array<string, string>
   *   The allowed line type options.
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
