<?php

namespace Drupal\Tests\organigram_block\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\organigram_block\Plugin\Block\OrganigramBlock;
use Drupal\user\Entity\User;

/**
 * Kernel integration tests for the OrganigramBlock plugin.
 *
 * @group organigram_block
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramBlockKernelTest extends KernelTestBase {

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
    'block',
    'organigram',
    'organigram_d3',
    'organigram_block',
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

    NodeType::create([
      'type' => 'organigram_node',
      'name' => 'Organigram node',
    ])->save();

    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    $this->rootNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Root',
      'uid' => 1,
      'status' => 1,
    ]);
    $this->rootNode->save();

    // Set active renderer.
    $this->config('organigram.settings')
      ->set('active_renderer', 'd3')
      ->save();
  }

  /**
   * Tests that the OrganigramBlock plugin is discoverable.
   */
  public function testBlockPluginDiscoverable(): void {
    $manager = $this->container->get('plugin.manager.block');
    $definition = $manager->getDefinition('organigram_block');
    $this->assertNotEmpty($definition);
    $this->assertSame('organigram_block', $definition['id']);
  }

  /**
   * Tests that build() with a valid root node returns a render array.
   */
  public function testBuildWithValidNodeReturnsRenderArray(): void {
    $block = $this->instantiateBlock(['root_node_id' => (int) $this->rootNode->id()]);
    $build = $block->build();

    $this->assertArrayHasKey('#theme', $build);
    $this->assertSame('organigram_display', $build['#theme']);
  }

  /**
   * Tests that build() without a root node returns an unconfigured message.
   */
  public function testBuildWithoutNodeReturnsMessage(): void {
    $block = $this->instantiateBlock(['root_node_id' => NULL]);
    $build = $block->build();

    $this->assertArrayHasKey('#type', $build);
    $this->assertSame('markup', $build['#type']);
  }

  /**
   * Tests that getCacheTags() includes the node list tag.
   */
  public function testGetCacheTagsIncludesListTag(): void {
    $block = $this->instantiateBlock(['root_node_id' => (int) $this->rootNode->id()]);
    $tags = $block->getCacheTags();

    $this->assertContains('node_list:organigram_node', $tags);
    $this->assertContains('node:' . $this->rootNode->id(), $tags);
    $this->assertContains('config:organigram.organigram_node_type_list', $tags);
  }

  /**
   * Instantiates an OrganigramBlock with the given configuration.
   *
   * @param array $configuration
   *   Block instance configuration.
   *
   * @return \Drupal\organigram_block\Plugin\Block\OrganigramBlock
   *   The block instance.
   */
  protected function instantiateBlock(array $configuration): OrganigramBlock {
    $manager = $this->container->get('plugin.manager.block');
    return $manager->createInstance('organigram_block', $configuration);
  }

}
