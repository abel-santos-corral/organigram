<?php

namespace Drupal\Tests\organigram\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the organigram JSON data endpoint.
 *
 * @group organigram
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramDataEndpointTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'image',
    'datetime',
    'options',
    'path',
    'link',
    'text',
    'user',
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
   * Root organigram node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $rootNode;

  /**
   * Child organigram node.
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
      'field_is_hidden' => 0,
      'field_parent_node' => ['target_id' => $this->rootNode->id()],
    ]);
    $this->childNode->save();
  }

  /**
   * Tests that the data endpoint returns HTTP 200.
   */
  public function testEndpointReturns200(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id() . '/data');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the response is valid JSON.
   */
  public function testEndpointReturnsValidJson(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id() . '/data');
    $content = $this->getSession()->getPage()->getContent();
    $decoded = json_decode($content, TRUE);
    $this->assertNotNull($decoded, 'Data endpoint returns valid JSON.');
  }

  /**
   * Tests that the JSON contains the expected top-level keys.
   */
  public function testJsonStructure(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id() . '/data');
    $decoded = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertArrayHasKey('meta', $decoded);
    $this->assertArrayHasKey('graph', $decoded);
    $this->assertArrayHasKey('visuals', $decoded);
    $this->assertArrayHasKey('nodes', $decoded['graph']);
    $this->assertArrayHasKey('edges', $decoded['graph']);
  }

  /**
   * Tests that the root node appears in the JSON graph.
   */
  public function testRootNodeInJson(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id() . '/data');
    $decoded = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertArrayHasKey(
      $this->rootNode->id(),
      $decoded['graph']['nodes'],
      'Root node present in JSON graph.'
    );
  }

  /**
   * Tests that the child node appears in the JSON graph.
   */
  public function testChildNodeInJson(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id() . '/data');
    $decoded = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertArrayHasKey(
      $this->childNode->id(),
      $decoded['graph']['nodes'],
      'Child node present in JSON graph.'
    );
  }

  /**
   * Tests that the response carries correct cache tags.
   */
  public function testResponseCacheTags(): void {
    $this->drupalGet('/organigram/' . $this->rootNode->id() . '/data');
    $tags = $this->getSession()->getResponseHeader('X-Drupal-Cache-Tags');

    $this->assertStringContainsString('node_list:organigram_node', $tags);
    $this->assertStringContainsString('node:' . $this->rootNode->id(), $tags);
    $this->assertStringContainsString('config:organigram.organigram_node_type_list', $tags);
  }

  /**
   * Tests the data endpoint cache HIT/MISS cycle.
   */
  public function testDataEndpointCacheCycle(): void {
    $url = '/organigram/' . $this->rootNode->id() . '/data';

    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    $this->childNode->setTitle('Updated');
    $this->childNode->save();

    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

}
