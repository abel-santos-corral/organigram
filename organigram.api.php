<?php

/**
 * @file
 * Organigram hooks.
 */

/**
 * Alter the generated organigram graph contract.
 *
 * @param array $graph
 *   The normalized graph contract.
 * @param array $context
 *   Additional graph build context.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_organigram_graph_alter(
  array &$graph,
  array &$context,
): void {

}

/**
 * @defgroup organigram_theming Organigram theming
 * @{
 * How to override Organigram templates in a theme or custom module.
 *
 * TEMPLATE: organigram-display.html.twig
 * ======================================
 * Registered by organigram_theme(). Renders the organigram chart container.
 *
 * Available variables:
 *   - root_node_id (int|null): NID of the root organigram node.
 *   - plugin_id (string|null): Active renderer plugin ID (e.g. 'd3').
 *   - settings (array): Renderer-specific settings passed by the block.
 *
 * Theme suggestion hierarchy (Drupal picks the most specific found):
 *   organigram-display.html.twig                  (module default)
 *   organigram-display--[plugin_id].html.twig     (e.g. --d3)
 *
 * To override the D3 renderer output in your theme, create:
 *   your_theme/templates/organigram-display--d3.html.twig
 *
 * TEMPLATE: block.html.twig (organigram block wrapper)
 * =====================================================
 * The organigram_block block plugin also receives renderer-specific
 * suggestions via organigram_theme_suggestions_block_alter(), so you can
 * override the full block wrapper per renderer.
 *
 * Theme suggestion hierarchy:
 *   block.html.twig
 *   block--organigram-block.html.twig
 *   block--organigram-block--[renderer].html.twig  (e.g. --d3)
 *
 * To override the block wrapper only when the D3 renderer is active:
 *   your_theme/templates/block--organigram-block--d3.html.twig
 * @}
 */

/**
 * Alter the theme suggestions for the organigram_display template.
 *
 * The module already adds organigram_display__[plugin_id] automatically.
 * Implement this hook if you need additional custom suggestions.
 *
 * @param array $suggestions
 *   An array of theme suggestions.
 * @param array $variables
 *   An array of variables passed to the theme hook. Relevant keys:
 *   - plugin_id: active renderer plugin ID (e.g. 'd3').
 *   - root_node_id: NID of the root organigram node.
 *   - settings: renderer-specific settings array.
 *
 * @see organigram_theme_suggestions_organigram_display_alter()
 * @ingroup organigram_theming
 */
function hook_theme_suggestions_organigram_display_alter(
  array &$suggestions,
  array $variables,
): void {
  // Example: add a suggestion based on the root node ID.
  if (!empty($variables['root_node_id'])) {
    $suggestions[] = 'organigram_display__node_' . $variables['root_node_id'];
  }
}
