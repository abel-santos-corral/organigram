<?php

namespace Drupal\Tests\organigram\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\organigram\Service\OrganigramGraphBuilder;
use Drupal\user\Entity\User;

/**
 * Tests OrganigramGraphBuilder::build() output structure.
 *
 * @group organigram
 * (PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramGraphBuilderKernelTest extends KernelTestBase {

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
    'organigram',
  ];

  /**
   * The root organigram node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $rootNode;

  /**
   * The child organigram node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $childNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installConfig(['system', 'node', 'organigram']);
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'organigram_node',
      'name' => 'Organigram node',
    ])->save();

    User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ])->save();

    $this->rootNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Root node',
      'uid' => 1,
      'status' => 1,
    ]);
    $this->rootNode->save();

    $this->childNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Child node',
      'uid' => 1,
      'status' => 1,
      'field_parent_node' => ['target_id' => $this->rootNode->id()],
    ]);
    $this->childNode->save();
  }

  /**
   * Tests that build() returns the expected top-level graph structure.
   */
  public function testBuildReturnsExpectedStructure(): void {
    $builder = $this->container->get('organigram.graph_builder');
    $this->assertInstanceOf(OrganigramGraphBuilder::class, $builder);

    $graph = $builder->build($this->rootNode);

    $this->assertArrayHasKey('meta', $graph);
    $this->assertArrayHasKey('graph', $graph);
    $this->assertArrayHasKey('visuals', $graph);
    $this->assertArrayHasKey('nodes', $graph['graph']);
    $this->assertArrayHasKey('edges', $graph['graph']);
  }

  /**
   * Tests that the root node appears in the graph nodes.
   */
  public function testRootNodeInGraph(): void {
    $builder = $this->container->get('organigram.graph_builder');
    $graph = $builder->build($this->rootNode);

    $this->assertArrayHasKey(
      $this->rootNode->id(),
      $graph['graph']['nodes'],
      'Root node is present in graph nodes.'
    );
    $this->assertEquals(
      'Root node',
      $graph['graph']['nodes'][$this->rootNode->id()]['title']
    );
  }

  /**
   * Tests that a child node appears in the graph nodes.
   */
  public function testChildNodeInGraph(): void {
    $builder = $this->container->get('organigram.graph_builder');
    $graph = $builder->build($this->rootNode);

    $this->assertArrayHasKey(
      $this->childNode->id(),
      $graph['graph']['nodes'],
      'Child node is present in graph nodes.'
    );
  }

  /**
   * Tests that an edge exists between root and child.
   */
  public function testEdgeBetweenRootAndChild(): void {
    $builder = $this->container->get('organigram.graph_builder');
    $graph = $builder->build($this->rootNode);

    $edge_ids = array_column($graph['graph']['edges'], 'id');
    $expected_edge = $this->rootNode->id() . '-' . $this->childNode->id();

    $this->assertContains($expected_edge, $edge_ids, 'Edge exists between root and child.');
  }

  /**
   * Tests that meta contains the correct root node ID.
   */
  public function testMetaContainsRootId(): void {
    $builder = $this->container->get('organigram.graph_builder');
    $graph = $builder->build($this->rootNode);

    $this->assertEquals((int) $this->rootNode->id(), $graph['meta']['root']);
  }

  /**
   * Tests that cacheability is populated when passed.
   */
  public function testCacheabilityPopulated(): void {
    $builder = $this->container->get('organigram.graph_builder');
    $cacheability = new CacheableMetadata();

    $builder->build($this->rootNode, $cacheability);

    $tags = $cacheability->getCacheTags();
    $this->assertContains('node:' . $this->rootNode->id(), $tags);
    $this->assertContains('config:organigram.organigram_node_type_list', $tags);
  }

}
