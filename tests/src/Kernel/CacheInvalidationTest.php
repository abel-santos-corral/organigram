<?php

namespace Drupal\Tests\organigram\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\organigram\Entity\OrganigramNodeType;
use Drupal\user\Entity\User;

/**
 * Tests cache tag invalidation on organigram entity CRUD operations.
 *
 * Covers:
 *  - organigram_node_insert / update / delete → node_list:organigram_node
 *  - organigram_node_type insert / update / delete →
 *    config:organigram.organigram_node_type_list
 *
 * @group organigram
 * (PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class CacheInvalidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'options',
    'datetime',
    'image',
    'file',
    'organigram',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'node', 'organigram']);
    $this->installSchema('node', ['node_access']);

    // Create a minimal user for node ownership.
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ])->save();
  }

  /**
   * Returns the current invalidation count for a cache tag.
   *
   * @param string $tag
   *   The cache tag to check.
   *
   * @return int
   *   The number of times the tag has been invalidated.
   */
  protected function getInvalidationCount(string $tag): int {
    $result = $this->container->get('database')
      ->select('cachetags', 'ct')
      ->fields('ct', ['invalidations'])
      ->condition('tag', $tag)
      ->execute()
      ->fetchField();

    return (int) ($result ?? 0);
  }

  /**
   * Tests that saving an organigram_node invalidates the list tag.
   */
  public function testNodeInsertInvalidatesListTag(): void {
    $before = $this->getInvalidationCount('node_list:organigram_node');

    Node::create([
      'type' => 'organigram_node',
      'title' => 'Test node',
      'uid' => 1,
      'status' => 1,
    ])->save();

    $after = $this->getInvalidationCount('node_list:organigram_node');
    $this->assertGreaterThan($before, $after, 'node_list:organigram_node invalidated on insert.');
  }

  /**
   * Tests that updating an organigram_node invalidates the list tag.
   */
  public function testNodeUpdateInvalidatesListTag(): void {
    $node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Test node',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    $before = $this->getInvalidationCount('node_list:organigram_node');

    $node->setTitle('Updated title');
    $node->save();

    $after = $this->getInvalidationCount('node_list:organigram_node');
    $this->assertGreaterThanOrEqual($before, $after, 'node_list:organigram_node invalidated on update.');
  }

  /**
   * Tests that deleting an organigram_node invalidates the list tag.
   */
  public function testNodeDeleteInvalidatesListTag(): void {
    $node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Test node',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    $before = $this->getInvalidationCount('node_list:organigram_node');

    $node->delete();

    $after = $this->getInvalidationCount('node_list:organigram_node');
    $this->assertGreaterThanOrEqual($before, $after, 'node_list:organigram_node invalidated on delete.');
  }

  /**
   * Tests that non-organigram nodes do not invalidate the list tag.
   */
  public function testNonOrganigramNodeDoesNotInvalidateListTag(): void {
    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();

    $before = $this->getInvalidationCount('node_list:organigram_node');

    Node::create([
      'type' => 'page',
      'title' => 'A page node',
      'uid' => 1,
      'status' => 1,
    ])->save();

    $after = $this->getInvalidationCount('node_list:organigram_node');
    $this->assertSame($before, $after, 'Non-organigram node insert must not invalidate organigram list tag.');
  }

  /**
   * Tests that inserting an OrganigramNodeType invalidates the config list tag.
   */
  public function testNodeTypeInsertInvalidatesConfigListTag(): void {
    $before = $this->getInvalidationCount('config:organigram.organigram_node_type_list');

    OrganigramNodeType::create([
      'id' => 'test_type',
      'label' => 'Test type',
    ])->save();

    $after = $this->getInvalidationCount('config:organigram.organigram_node_type_list');
    $this->assertGreaterThan($before, $after, 'config list tag invalidated on OrganigramNodeType insert.');
  }

  /**
   * Tests that updating an OrganigramNodeType invalidates the config list tag.
   */
  public function testNodeTypeUpdateInvalidatesConfigListTag(): void {
    $node_type = OrganigramNodeType::create([
      'id' => 'test_type',
      'label' => 'Test type',
    ]);
    $node_type->save();

    $before = $this->getInvalidationCount('config:organigram.organigram_node_type_list');

    $node_type->set('label', 'Updated label');
    $node_type->save();

    $after = $this->getInvalidationCount('config:organigram.organigram_node_type_list');
    $this->assertGreaterThanOrEqual($before, $after, 'config list tag invalidated on OrganigramNodeType update.');
  }

  /**
   * Tests that deleting an OrganigramNodeType invalidates the config list tag.
   */
  public function testNodeTypeDeleteInvalidatesConfigListTag(): void {
    $node_type = OrganigramNodeType::create([
      'id' => 'test_type',
      'label' => 'Test type',
    ]);
    $node_type->save();

    $before = $this->getInvalidationCount('config:organigram.organigram_node_type_list');

    $node_type->delete();

    $after = $this->getInvalidationCount('config:organigram.organigram_node_type_list');
    $this->assertGreaterThanOrEqual($before, $after, 'config list tag invalidated on OrganigramNodeType delete.');
  }

}
