<?php

namespace Drupal\Tests\organigram_block\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\organigram\OrganigramRendererManager;
use Drupal\organigram\Service\OrganigramGraphBuilder;
use Drupal\organigram_block\Plugin\Block\OrganigramBlock;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the OrganigramBlock plugin.
 *
 * @group organigram_block
 * @coversDefaultClass \Drupal\organigram_block\Plugin\Block\OrganigramBlock
 */
class OrganigramBlockUnitTest extends UnitTestCase {

  /**
   * Builds an OrganigramBlock with mocked dependencies.
   *
   * @param array $configuration
   *   Block instance configuration overrides.
   * @param \Drupal\node\NodeInterface|null $node
   *   Node to return from entity storage load, or NULL.
   *
   * @return \Drupal\organigram_block\Plugin\Block\OrganigramBlock
   *   The block instance.
   */
  protected function buildBlock(array $configuration = [], ?NodeInterface $node = NULL): OrganigramBlock {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($node);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($storage);

    $graph_builder = $this->createMock(OrganigramGraphBuilder::class);
    $graph_builder->method('build')->willReturn([
      'meta' => ['root' => 1],
      'graph' => ['nodes' => [], 'edges' => []],
      'visuals' => [],
    ]);

    $config = $this->createMock(Config::class);
    $config->method('get')->with('active_renderer')->willReturn('d3');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);

    $renderer_manager = $this->createMock(OrganigramRendererManager::class);
    $renderer_manager->method('getDefinitions')->willReturn(['d3' => ['label' => 'D3.js']]);

    $defaults = ['root_node_id' => NULL];
    $merged_config = array_merge($defaults, $configuration);

    $current_user = $this->createMock(
      \Drupal\Core\Session\AccountProxyInterface::class
    );

    $block = new OrganigramBlock(
      $merged_config,
      'organigram_block',
      [
        'id' => 'organigram_block',
        'admin_label' => 'Organigram',
        'category' => 'Organigram',
        'provider' => 'organigram_block',
      ],
      $entity_type_manager,
      $graph_builder,
      $renderer_manager,
      $config_factory,
      $current_user,
    );

    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('hasPermission')->willReturn(FALSE);

    $block->setStringTranslation($this->getStringTranslationStub());

    return $block;
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationHasRootNodeId(): void {
    $block = $this->buildBlock();
    $config = $block->defaultConfiguration();
    $this->assertArrayHasKey('root_node_id', $config);
    $this->assertNull($config['root_node_id']);
  }

  /**
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithoutNodeReturnsParentTags(): void {
    $block = $this->buildBlock(['root_node_id' => NULL], NULL);
    $tags = $block->getCacheTags();
    $this->assertNotContains('node_list:organigram_node', $tags);
  }

  /**
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithNodeIncludesListTag(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getCacheTags')->willReturn(['node:1']);

    $block = $this->buildBlock(['root_node_id' => 1], $node);
    $tags = $block->getCacheTags();

    $this->assertContains('node_list:organigram_node', $tags);
    $this->assertContains('node:1', $tags);
    $this->assertContains('config:organigram.organigram_node_type_list', $tags);
  }

  /**
   * @covers ::getCacheContexts
   */
  public function testGetCacheContextsIncludesUserPermissions(): void {
    $block = $this->buildBlock();
    $this->assertContains('user.permissions', $block->getCacheContexts());
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidateFailsWhenNodeIsNull(): void {
    $block = $this->buildBlock(['root_node_id' => 999], NULL);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->with('root_node_id')
      ->willReturn(999);
    $form_state->expects($this->once())
      ->method('setErrorByName');

    $block->blockValidate($form, $form_state);
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidateFailsWhenWrongBundle(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('page');

    $block = $this->buildBlock(['root_node_id' => 1], $node);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->with('root_node_id')
      ->willReturn(1);
    $form_state->expects($this->once())
      ->method('setErrorByName');

    $block->blockValidate($form, $form_state);
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidatePassesWithCorrectBundle(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('organigram_node');

    $block = $this->buildBlock(['root_node_id' => 1], $node);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->with('root_node_id')
      ->willReturn(1);
    $form_state->expects($this->never())
      ->method('setErrorByName');

    $block->blockValidate($form, $form_state);
  }

  /**
   * @covers ::blockSubmit
   */
  public function testBlockSubmitSavesRootNodeId(): void {
    $block = $this->buildBlock();

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->with('root_node_id')
      ->willReturn('42');

    $block->blockSubmit($form, $form_state);
    $this->assertSame(42, $block->getConfiguration()['root_node_id']);
  }

}
