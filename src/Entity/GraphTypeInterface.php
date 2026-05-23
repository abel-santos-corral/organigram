<?php

namespace Drupal\organigram\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for Graph Type config entities.
 *
 * A Graph Type defines the visual appearance of a graph_node in the
 * organigram: box styling (font, colour, background) and connector line
 * styling (weight, colour, dash pattern).
 */
interface GraphTypeInterface extends ConfigEntityInterface {

  // ── Box ────────────────────────────────────────────────────────────────────

  /**
   * Returns the node box font size in pixels.
   */
  public function getBoxFontSize(): int;

  /**
   * Returns the node box font colour as a hex string (e.g. #1a1a18).
   */
  public function getBoxFontColor(): string;

  /**
   * Returns the node box background colour as a hex string (e.g. #ffffff).
   */
  public function getBoxBackground(): string;

  // ── Line ───────────────────────────────────────────────────────────────────

  /**
   * Returns the connector line width as a string (e.g. '0.5', '1', '2').
   */
  public function getLineSize(): string;

  /**
   * Returns the connector line colour as a hex string (e.g. #cccccc).
   */
  public function getLineColor(): string;

  /**
   * Returns the connector line type: solid | dashed | dotted | dashdot.
   */
  public function getLineType(): string;

  // ── Helpers ────────────────────────────────────────────────────────────────

  /**
   * Returns the SVG stroke-dasharray value for the configured line type.
   *
   * Ready to drop straight into a D3 .attr('stroke-dasharray', ...) call.
   */
  public function getLineDashArray(): string;

}
