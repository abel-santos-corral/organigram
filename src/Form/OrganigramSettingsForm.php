<?php

namespace Drupal\organigram\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\organigram\OrganigramRendererManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Global Organigram settings form.
 *
 * Exposes the active renderer selector, populated dynamically from all
 * enabled OrganigramRenderer plugins.  Renderer-specific settings (e.g.
 * canvas dimensions, zoom behaviour) live in each renderer submodule's
 * own settings form.
 */
class OrganigramSettingsForm extends ConfigFormBase {

  /**
   * The organigram renderer plugin manager.
   */
  protected OrganigramRendererManager $rendererManager;

  /**
   * Constructs an OrganigramSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\organigram\OrganigramRendererManager $renderer_manager
   *   The organigram renderer plugin manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    OrganigramRendererManager $renderer_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->rendererManager = $renderer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.organigram_renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'organigram_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['organigram.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('organigram.settings');

    $options = [];
    foreach ($this->rendererManager->getDefinitions() as $id => $definition) {
      $options[$id] = (string) $definition['label'];
    }

    if (empty($options)) {
      $form['no_renderers'] = [
        '#type' => 'markup',
        '#markup' => $this->t(
          'No renderer modules are currently enabled. Enable a renderer
          submodule such as <em>Organigram D3</em> to select it here.'
        ),
      ];

      return $form;
    }

    $form['active_renderer'] = [
      '#type' => 'select',
      '#title' => $this->t('Active renderer'),
      '#description' => $this->t(
        'The renderer used to display all organigrams. Enable additional
        renderer submodules to add more options.'
      ),
      '#options' => $options,
      '#default_value' => $config->get('active_renderer'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('organigram.settings')
      ->set('active_renderer', $form_state->getValue('active_renderer'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
