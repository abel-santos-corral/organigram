<?php

namespace Drupal\organigram_d3\Plugin\OrganigramRenderer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\organigram\Plugin\OrganigramRenderer\OrganigramRendererBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * D3.js organigram renderer.
 *
 * Renders the organigram as an interactive, zoomable SVG tree using D3 v7.
 * Canvas dimensions, modal size, zoom behaviour, and collapse depth are
 * configurable at /admin/config/organigram/d3.
 *
 * @OrganigramRenderer(
 *   id = "d3",
 *   label = @Translation("D3.js"),
 *   description = @Translation("Interactive zoomable SVG tree using D3 v7. Supports collapsible nodes, a detail modal, and a visual legend."),
 * )
 */
class D3Renderer extends OrganigramRendererBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a D3Renderer object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(
    NodeInterface $root,
    array $graph,
    array $settings = [],
  ): array {
    $d3config = $this->configFactory->get('organigram_d3.settings');

    return [
      '#theme' => 'organigram_display',
      '#root_node_id' => $root->id(),
      '#settings' => $settings,
      '#plugin_id' => 'd3',
      '#attached' => [
        'library' => ['organigram_d3/organigram'],
        'drupalSettings' => [
          'organigram' => [
            'graph' => $graph,
            'rootId' => (int) $root->id(),
            'legendTitle' => (string) $this->t('Legend'),
            'config' => [
              'height' => (int) $d3config->get('height'),
              'width' => (int) $d3config->get('width'),
              'modalHeight' => (int) $d3config->get('modal_height'),
              'modalWidth' => (int) $d3config->get('modal_width'),
              'zoomEnabled' => (bool) $d3config->get('zoom_enabled'),
              'zoomMin' => (float) $d3config->get('zoom_min'),
              'zoomMax' => (float) $d3config->get('zoom_max'),
              'collapseDepth' => (int) $d3config->get('collapse_depth'),
            ],
          ],
        ],
      ],
      '#cache' => [
        'tags' => array_merge(
          $root->getCacheTags(),
          ['node_list:organigram_node'],
          ['config:organigram.organigram_node_type_list'],
          ['config:organigram_d3.settings'],
        ),
      ],
    ];
  }

}
