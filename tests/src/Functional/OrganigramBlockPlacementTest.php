<?php

namespace Drupal\Tests\organigram\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests organigram block placement and rendering via the block UI.
 *
 * @group organigram
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramBlockPlacementTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'image',
    'block',
    'datetime',
    'options',
    'path',
    'link',
    'text',
    'user',
    'organigram',
    'organigram_d3',
    'organigram_block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with block and organigram permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

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

    $this->rootNode = Node::create([
      'type' => 'organigram_node',
      'title' => 'Block root',
      'uid' => 1,
      'status' => 1,
    ]);
    $this->rootNode->save();

    \Drupal::configFactory()
      ->getEditable('organigram.settings')
      ->set('active_renderer', 'd3')
      ->save();

    $this->adminUser = $this->drupalCreateUser([
      'administer blocks',
      'administer organigram',
      'access content',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests placing an organigram block and verifying it renders on the page.
   */
  public function testPlacedBlockRendersOrganigram(): void {
    // Place the block programmatically to avoid browser JS dependency.
    $this->drupalPlaceBlock('organigram_block', [
      'root_node_id' => (int) $this->rootNode->id(),
      'region' => 'content',
    ]);

    $this->drupalGet('<front>');
    $this->assertSession()->elementExists('css', '#organigram-container');
  }

  /**
   * Tests that two blocks placed with different root nodes render independently.
   */
  public function testTwoBlocksWithDifferentRoots(): void {
    $second_node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Second root',
      'uid' => 1,
      'status' => 1,
    ]);
    $second_node->save();

    $this->drupalPlaceBlock('organigram_block', [
      'id' => 'organigram_block_first',
      'root_node_id' => (int) $this->rootNode->id(),
      'region' => 'content',
    ]);

    $this->drupalPlaceBlock('organigram_block', [
      'id' => 'organigram_block_second',
      'root_node_id' => (int) $second_node->id(),
      'region' => 'sidebar_first',
    ]);

    $this->drupalGet('<front>');
    // Both blocks render without fatal errors.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that an unconfigured block shows nothing to anonymous users.
   */
  public function testUnconfiguredBlockHiddenFromAnonymous(): void {
    $this->drupalPlaceBlock('organigram_block', [
      'root_node_id' => NULL,
      'region' => 'content',
    ]);

    $this->drupalLogout();
    $this->drupalGet('<front>');

    // No organigram container rendered for anonymous when block unconfigured.
    $this->assertSession()->elementNotExists('css', '#organigram-container');
  }

}
