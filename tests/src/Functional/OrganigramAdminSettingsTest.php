<?php

namespace Drupal\Tests\organigram\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Organigram admin settings forms.
 *
 * Covers:
 *  - /admin/config/organigram (core settings, renderer selector)
 *  - /admin/config/organigram/d3 (D3 renderer settings)
 *
 * @group organigram
 * (PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class OrganigramAdminSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'organigram',
    'organigram_d3',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An admin user with the administer organigram permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer organigram']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the core settings form is accessible and saves correctly.
   */
  public function testCoreSettingsFormSavesActiveRenderer(): void {
    $this->drupalGet('/admin/config/organigram');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('active_renderer');

    $this->submitForm(['active_renderer' => 'd3'], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $saved = \Drupal::config('organigram.settings')->get('active_renderer');
    $this->assertSame('d3', $saved);
  }

  /**
   * Tests that the D3 settings form is accessible.
   */
  public function testD3SettingsFormAccessible(): void {
    $this->drupalGet('/admin/config/organigram/d3');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the D3 settings form saves all fields correctly.
   */
  public function testD3SettingsFormSavesValues(): void {
    $this->drupalGet('/admin/config/organigram/d3');

    $this->submitForm([
      'height' => 800,
      'width' => 90,
      'modal_height' => 600,
      'modal_width' => 800,
      'zoom_enabled' => TRUE,
      'zoom_min' => 0.2,
      'zoom_max' => 4.0,
      'collapse_depth' => 3,
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = \Drupal::config('organigram_d3.settings');
    $this->assertSame(800, $config->get('height'));
    $this->assertSame(90, $config->get('width'));
    $this->assertSame(600, $config->get('modal_height'));
    $this->assertSame(800, $config->get('modal_width'));
    $this->assertTrue($config->get('zoom_enabled'));
    $this->assertEqualsWithDelta(0.2, $config->get('zoom_min'), 0.001);
    $this->assertEqualsWithDelta(4.0, $config->get('zoom_max'), 0.001);
    $this->assertSame(3, $config->get('collapse_depth'));
  }

  /**
   * Tests that anonymous users cannot access the settings forms.
   */
  public function testAnonymousCannotAccessSettings(): void {
    $this->drupalLogout();

    $this->drupalGet('/admin/config/organigram');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/organigram/d3');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the D3 settings link appears under the core settings page.
   */
  public function testD3SettingsLinkAppearsInMenu(): void {
    $this->drupalGet('/admin/config/organigram');
    $this->assertSession()->linkByHrefExists('/admin/config/organigram/d3');
  }

}
