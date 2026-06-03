<?php

namespace Drupal\Tests\organigram_d3\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\organigram_d3\Plugin\OrganigramRenderer\D3Renderer;
use Drupal\user\Entity\User;

/**
 * Kernel integration tests for the D3Renderer plugin.
 *
 * Verifies that the renderer produces correct render arrays with real
 * Drupal services and a real node tree.
 *
 * @group organigram_d3
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class D3RendererIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'file',
    'image',
    'datetime',
    'link',
    'options',
    'organigram',
    'organigram_d3',
  ];

  /**
   * A root organigram node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $rootNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installConfig(['system', 'node', 'organigram', 'organigram_d3']);
    $this->installSchema('node', ['node_access']);

    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    $this->rootNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Root',
      'uid' => 1,
      'status' => 1,
    ]);
    $this->rootNode->save();
  }

  /**
   * Tests that D3Renderer::render() returns the expected render array keys.
   */
  public function testRenderReturnsExpectedKeys(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $renderer = $manager->createInstance('d3');
    $this->assertInstanceOf(D3Renderer::class, $renderer);

    $graph_builder = $this->container->get('organigram.graph_builder');
    $graph = $graph_builder->build($this->rootNode);

    $build = $renderer->render($this->rootNode, $graph);

    $this->assertArrayHasKey('#theme', $build);
    $this->assertArrayHasKey('#attached', $build);
    $this->assertArrayHasKey('#cache', $build);
    $this->assertSame('organigram_display', $build['#theme']);
  }

  /**
   * Tests that the render array passes the graph to drupalSettings.
   */
  public function testRenderPassesGraphToDrupalSettings(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $renderer = $manager->createInstance('d3');

    $graph_builder = $this->container->get('organigram.graph_builder');
    $graph = $graph_builder->build($this->rootNode);

    $build = $renderer->render($this->rootNode, $graph);
    $settings = $build['#attached']['drupalSettings']['organigram'];

    $this->assertArrayHasKey('graph', $settings);
    $this->assertArrayHasKey('config', $settings);
    $this->assertArrayHasKey('rootId', $settings);
    $this->assertSame((int) $this->rootNode->id(), $settings['rootId']);
  }

  /**
   * Tests that D3 settings config values reach drupalSettings.config.
   */
  public function testD3ConfigReachesdrupalSettings(): void {
    // Override a config value.
    $this->config('organigram_d3.settings')->set('height', 999)->save();

    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $renderer = $manager->createInstance('d3');

    $graph_builder = $this->container->get('organigram.graph_builder');
    $graph = $graph_builder->build($this->rootNode);
    $build = $renderer->render($this->rootNode, $graph);

    $this->assertSame(999, $build['#attached']['drupalSettings']['organigram']['config']['height']);
  }

  /**
   * Tests that the render array carries the D3 settings cache tag.
   */
  public function testRenderIncludesD3SettingsCacheTag(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $renderer = $manager->createInstance('d3');
    $graph = $this->container->get('organigram.graph_builder')->build($this->rootNode);
    $build = $renderer->render($this->rootNode, $graph);

    $this->assertContains('config:organigram_d3.settings', $build['#cache']['tags']);
  }

  /**
   * Tests that changing D3 settings invalidates the config tag.
   */
  public function testD3SettingsUpdateInvalidatesConfigTag(): void {
    $before = $this->container->get('database')
      ->select('cachetags', 'ct')
      ->fields('ct', ['invalidations'])
      ->condition('tag', 'config:organigram_d3.settings')
      ->execute()
      ->fetchField();

    $this->config('organigram_d3.settings')->set('height', 800)->save();

    $after = $this->container->get('database')
      ->select('cachetags', 'ct')
      ->fields('ct', ['invalidations'])
      ->condition('tag', 'config:organigram_d3.settings')
      ->execute()
      ->fetchField();

    $this->assertGreaterThan((int) $before, (int) $after);
  }

}
