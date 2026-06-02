<?php

namespace Drupal\Tests\organigram\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\organigram\OrganigramRendererManager;
use Drupal\organigram_d3\Plugin\OrganigramRenderer\D3Renderer;

/**
 * Tests OrganigramRenderer plugin discovery and instantiation.
 *
 * @group organigram
 * (PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramRendererPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'organigram',
    'organigram_d3',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['organigram', 'organigram_d3']);
  }

  /**
   * Tests that the plugin manager service is available.
   */
  public function testManagerServiceExists(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $this->assertInstanceOf(OrganigramRendererManager::class, $manager);
  }

  /**
   * Tests that D3Renderer is discovered when organigram_d3 is enabled.
   */
  public function testD3RendererDiscovered(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $definitions = $manager->getDefinitions();

    $this->assertArrayHasKey('d3', $definitions, 'D3Renderer plugin is discoverable.');
    $this->assertEquals('D3.js', (string) $definitions['d3']['label']);
  }

  /**
   * Tests that D3Renderer can be instantiated via the manager.
   */
  public function testD3RendererInstantiation(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $renderer = $manager->createInstance('d3');

    $this->assertInstanceOf(D3Renderer::class, $renderer);
    $this->assertEquals('D3.js', $renderer->label());
  }

  /**
   * Tests that the manager returns an empty set when no renderers are enabled.
   *
   * This is tested by getting definitions before any renderer module is
   * enabled — in this test the organigram_d3 module IS enabled so we verify
   * the opposite: definitions must not be empty.
   */
  public function testDefinitionsNotEmptyWithD3Enabled(): void {
    $manager = $this->container->get('plugin.manager.organigram_renderer');
    $this->assertNotEmpty($manager->getDefinitions());
  }

}
