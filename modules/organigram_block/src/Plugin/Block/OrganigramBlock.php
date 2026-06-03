<?php

namespace Drupal\organigram_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\organigram\OrganigramRendererManager;
use Drupal\organigram\Service\OrganigramGraphBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configurable organigram block.
 *
 * Each block instance has its own root node, so multiple blocks can be
 * placed on different pages showing different organigram trees.  Rendering
 * is delegated to the active OrganigramRenderer plugin configured at
 * /admin/config/organigram, keeping the block renderer-agnostic.
 *
 * Cacheability:
 *  - Cache tags cover the root node, all organigram_node list changes, and
 *    all organigram_node_type config entity changes, so the block is
 *    invalidated precisely when any of those entities is saved or deleted.
 *  - Cache contexts include the block instance ID so each placed block
 *    instance is cached independently.
 *
 * @Block(
 *   id = "organigram_block",
 *   admin_label = @Translation("Organigram"),
 *   category = @Translation("Organigram"),
 * )
 */
class OrganigramBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The organigram graph builder.
   */
  protected OrganigramGraphBuilder $graphBuilder;

  /**
   * The organigram renderer plugin manager.
   */
  protected OrganigramRendererManager $rendererManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs an OrganigramBlock object.
   *
   * @param array $configuration
   *   Plugin instance configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\organigram\Service\OrganigramGraphBuilder $graph_builder
   *   The organigram graph builder.
   * @param \Drupal\organigram\OrganigramRendererManager $renderer_manager
   *   The organigram renderer plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    OrganigramGraphBuilder $graph_builder,
    OrganigramRendererManager $renderer_manager,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->graphBuilder = $graph_builder;
    $this->rendererManager = $renderer_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('organigram.graph_builder'),
      $container->get('plugin.manager.organigram_renderer'),
      $container->get('config.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'root_node_id' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $root_node_id = $this->configuration['root_node_id'];

    $form['root_node_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Root node'),
      '#description' => $this->t(
        'Select the organigram_node that will be the root of this organigram block.'
      ),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['organigram_node'],
      ],
      '#default_value' => $root_node_id
        ? $this->entityTypeManager->getStorage('node')->load($root_node_id)
        : NULL,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $node_id = $form_state->getValue('root_node_id');

    if (empty($node_id)) {
      $form_state->setErrorByName('root_node_id', $this->t('A root node is required.'));
      return;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($node_id);

    if (!$node instanceof NodeInterface) {
      $form_state->setErrorByName('root_node_id', $this->t('The selected node does not exist.'));
      return;
    }

    if ($node->bundle() !== 'organigram_node') {
      $form_state->setErrorByName(
        'root_node_id',
        $this->t('The selected node must be of type <em>Organigram node</em>.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['root_node_id'] = (int) $form_state->getValue('root_node_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->loadRootNode();

    if (!$node instanceof NodeInterface) {
      return $this->buildUnconfiguredMessage();
    }

    $graph = $this->graphBuilder->build($node);
    $renderer = $this->getActiveRenderer();

    return $renderer->render($node, $graph);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $node = $this->loadRootNode();

    if (!$node instanceof NodeInterface) {
      return parent::getCacheTags();
    }

    return array_unique(array_merge(
      parent::getCacheTags(),
      $node->getCacheTags(),
      ['node_list:organigram_node'],
      ['config:organigram.organigram_node_type_list'],
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_unique(array_merge(parent::getCacheContexts(), ['user.permissions']));
  }

  /**
   * Loads the configured root node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The root node, or NULL when not configured or no longer available.
   */
  protected function loadRootNode(): ?NodeInterface {
    $node_id = $this->configuration['root_node_id'] ?? NULL;

    if (empty($node_id)) {
      return NULL;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($node_id);

    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Returns a placeholder render array when the block is not yet configured.
   *
   * Only visible to users with block administration access so anonymous
   * visitors never see the placeholder.
   *
   * @return array
   *   The placeholder render array.
   */
  protected function buildUnconfiguredMessage(): array {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Organigram block: no root node configured.'),
      '#access' => $this->currentUser->hasPermission('administer blocks'),
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Returns the active renderer plugin instance.
   *
   * Falls back to the first available renderer when the configured one is
   * absent so the block does not crash after a renderer module is uninstalled.
   *
   * @return \Drupal\organigram\OrganigramRendererInterface
   *   The renderer plugin instance.
   */
  protected function getActiveRenderer() {
    $active = $this->configFactory
      ->get('organigram.settings')
      ->get('active_renderer');

    $definitions = $this->rendererManager->getDefinitions();

    if (empty($active) || !isset($definitions[$active])) {
      $active = array_key_first($definitions);
    }

    return $this->rendererManager->createInstance($active);
  }

}
