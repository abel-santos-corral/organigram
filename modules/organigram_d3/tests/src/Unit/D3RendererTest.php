<?php

namespace Drupal\Tests\organigram_d3\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Drupal\organigram_d3\Plugin\OrganigramRenderer\D3Renderer;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the D3Renderer plugin.
 *
 * @group organigram_d3
 * @coversDefaultClass \Drupal\organigram_d3\Plugin\OrganigramRenderer\D3Renderer
 */
class D3RendererTest extends UnitTestCase {

  /**
   * Returns a D3Renderer instance with mocked dependencies.
   *
   * @param array $config_values
   *   Config values to return from organigram_d3.settings.
   *
   * @return \Drupal\organigram_d3\Plugin\OrganigramRenderer\D3Renderer
   *   The renderer instance.
   */
  protected function buildRenderer(array $config_values = []): D3Renderer {
    $defaults = [
      'height' => 600,
      'width' => 100,
      'modal_height' => 500,
      'modal_width' => 700,
      'zoom_enabled' => TRUE,
      'zoom_min' => 0.1,
      'zoom_max' => 3.0,
      'collapse_depth' => 2,
    ];

    $values = array_merge($defaults, $config_values);

    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(
      fn ($key) => $values[$key] ?? NULL
    );

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('organigram_d3.settings')
      ->willReturn($config);

    $renderer = new D3Renderer(
      [],
      'd3',
      [
        'id' => 'd3',
        'label' => 'D3.js',
        'description' => '',
      ],
      $config_factory,
    );

    $renderer->setStringTranslation(
      $this->getStringTranslationStub()
    );

    return $renderer;
  }

  /**
   * Returns a mocked root NodeInterface.
   *
   * @return \Drupal\node\NodeInterface
   *   The mocked node.
   */
  protected function buildNode(): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn('1');
    $node->method('getCacheTags')->willReturn(['node:1']);
    return $node;
  }

  /**
   * @covers ::render
   */
  public function testRenderReturnsThemeKey(): void {
    $renderer = $this->buildRenderer();
    $build = $renderer->render($this->buildNode(), []);
    $this->assertSame('organigram_display', $build['#theme']);
  }

  /**
   * @covers ::render
   */
  public function testRenderAttachesCorrectLibrary(): void {
    $renderer = $this->buildRenderer();
    $build = $renderer->render($this->buildNode(), []);
    $this->assertContains('organigram_d3/organigram', $build['#attached']['library']);
  }

  /**
   * @covers ::render
   */
  public function testRenderPassesGraphToDrupalSettings(): void {
    $renderer = $this->buildRenderer();
    $graph = ['meta' => ['root' => 1], 'graph' => ['nodes' => [], 'edges' => []], 'visuals' => []];
    $build = $renderer->render($this->buildNode(), $graph);
    $this->assertSame($graph, $build['#attached']['drupalSettings']['organigram']['graph']);
  }

  /**
   * @covers ::render
   */
  public function testRenderPassesConfigToDrupalSettings(): void {
    $renderer = $this->buildRenderer(['height' => 800, 'zoom_enabled' => FALSE]);
    $build = $renderer->render($this->buildNode(), []);
    $config = $build['#attached']['drupalSettings']['organigram']['config'];

    $this->assertSame(800, $config['height']);
    $this->assertFalse($config['zoomEnabled']);
  }

  /**
   * @covers ::render
   */
  public function testRenderIncludesNodeCacheTag(): void {
    $renderer = $this->buildRenderer();
    $build = $renderer->render($this->buildNode(), []);
    $this->assertContains('node:1', $build['#cache']['tags']);
  }

  /**
   * @covers ::render
   */
  public function testRenderIncludesNodeListCacheTag(): void {
    $renderer = $this->buildRenderer();
    $build = $renderer->render($this->buildNode(), []);
    $this->assertContains('node_list:organigram_node', $build['#cache']['tags']);
  }

  /**
   * @covers ::render
   */
  public function testRenderIncludesNodeTypeConfigListTag(): void {
    $renderer = $this->buildRenderer();
    $build = $renderer->render($this->buildNode(), []);
    $this->assertContains('config:organigram.organigram_node_type_list', $build['#cache']['tags']);
  }

  /**
   * @covers ::render
   */
  public function testRenderIncludesD3SettingsConfigTag(): void {
    $renderer = $this->buildRenderer();
    $build = $renderer->render($this->buildNode(), []);
    $this->assertContains('config:organigram_d3.settings', $build['#cache']['tags']);
  }

  /**
   * @covers ::label
   */
  public function testLabel(): void {
    $renderer = $this->buildRenderer();
    $this->assertSame('D3.js', $renderer->label());
  }

}
