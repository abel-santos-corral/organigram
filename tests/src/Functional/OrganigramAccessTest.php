<?php

namespace Drupal\Tests\organigram\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests access control for organigram pages.
 *
 * @group organigram
 * (PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramAccessTest extends BrowserTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that anonymous users can view a published organigram node.
   */
  public function testAnonymousCanViewPublishedOrganigram(): void {
    $node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Public organigram',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    $this->drupalGet('/organigram/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that anonymous users cannot view an unpublished organigram node.
   */
  public function testAnonymousCannotViewUnpublishedOrganigram(): void {
    $node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Private organigram',
      'uid' => 1,
      'status' => 0,
    ]);
    $node->save();

    $this->drupalGet('/organigram/' . $node->id());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the JSON data endpoint is accessible for published nodes.
   */
  public function testDataEndpointAccessibleForPublishedNode(): void {
    $node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Public organigram',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    $this->drupalGet('/organigram/' . $node->id() . '/data');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the JSON data endpoint returns valid JSON.
   */
  public function testDataEndpointReturnsJson(): void {
    $node = Node::create([
      'type' => 'organigram_node',
      'title' => 'Public organigram',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    $this->drupalGet('/organigram/' . $node->id() . '/data');
    $response = $this->getSession()->getPage()->getContent();
    $decoded = json_decode($response, TRUE);

    $this->assertNotNull($decoded, 'Data endpoint returns valid JSON.');
    $this->assertArrayHasKey('graph', $decoded);
    $this->assertArrayHasKey('meta', $decoded);
  }

}
