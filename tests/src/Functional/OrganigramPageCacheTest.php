<?php

namespace Drupal\Tests\organigram\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the page cache HIT/MISS cycle for organigram pages.
 *
 * Verifies that:
 *  - The organigram page is cached after the first anonymous request.
 *  - Saving an organigram_node invalidates the page cache.
 *  - Saving an OrganigramNodeType invalidates the page cache.
 *
 * @group organigram
 * (PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramPageCacheTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'organigram',
    'organigram_d3',
    'page_cache',
    'dynamic_page_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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

    $this->rootNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Root',
      'uid' => 1,
      'status' => 1,
    ]);
    $this->rootNode->save();

    $this->childNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Child',
      'uid' => 1,
      'status' => 1,
      'field_parent_node' => ['target_id' => $this->rootNode->id()],
    ]);
    $this->childNode->save();

    // Set active renderer to d3.
    \Drupal::configFactory()
      ->getEditable('organigram.settings')
      ->set('active_renderer', 'd3')
      ->save();
  }

  /**
   * Tests the full MISS → HIT → invalidate → MISS cycle.
   */
  public function testPageCacheCycle(): void {
    $url = '/organigram/' . $this->rootNode->id();

    // First request: MISS.
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    // Second request: HIT.
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    // Save a child node — should bust the cache.
    $this->childNode->setTitle('Updated child');
    $this->childNode->save();

    // Next request: MISS again.
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

  /**
   * Tests that updating an OrganigramNodeType invalidates the page cache.
   */
  public function testNodeTypeUpdateBustsCache(): void {
    $node_type = \Drupal\organigram\Entity\OrganigramNodeType::create([
      'id' => 'dept',
      'label' => 'Department',
    ]);
    $node_type->save();

    $url = '/organigram/' . $this->rootNode->id();

    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    // Update the node type.
    $node_type->set('label', 'Updated Department');
    $node_type->save();

    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

  /**
   * Tests that the response carries the expected cache tags.
   */
  public function testResponseHasCorrectCacheTags(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id());

    $tags = $this->getSession()->getResponseHeader('X-Drupal-Cache-Tags');
    $this->assertStringContainsString('node_list:organigram_node', $tags);
    $this->assertStringContainsString('node:' . $this->rootNode->id(), $tags);
    $this->assertStringContainsString('config:organigram.organigram_node_type_list', $tags);
  }

}
