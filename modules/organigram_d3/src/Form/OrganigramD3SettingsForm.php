<?php

namespace Drupal\organigram_d3\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Organigram D3 renderer.
 *
 * Exposes canvas dimensions, modal size, zoom behaviour, and the default
 * collapse depth.  All values are passed to drupalSettings.organigram.config
 * by D3Renderer and consumed by organigram.js.
 */
class OrganigramD3SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'organigram_d3_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['organigram_d3.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('organigram_d3.settings');
    $form['canvas']    = $this->buildCanvasFieldset($config->get('height'), $config->get('width'));
    $form['modal']     = $this->buildModalFieldset($config->get('modal_width'), $config->get('modal_height'));
    $form['zoom']      = $this->buildZoomFieldset($config->get('zoom_enabled'), $config->get('zoom_min'), $config->get('zoom_max'));
    $form['behaviour'] = $this->buildBehaviourFieldset($config->get('collapse_depth'));

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the canvas fieldset.
   *
   * @param int $height
   *   Current canvas height.
   * @param int $width
   *   Current canvas width.
   *
   * @return array
   *   The canvas fieldset render array.
   */
  protected function buildCanvasFieldset(int $height, int $width): array {
    return [
      '#type' => 'fieldset',
      '#title' => $this->t('Canvas'),
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Height (px)'),
        '#description' => $this->t('Minimum canvas height in pixels.'),
        '#default_value' => $height,
        '#min' => 100,
        '#max' => 4000,
        '#required' => TRUE,
      ],
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Width (%)'),
        '#description' => $this->t('Canvas width as a percentage of its container.'),
        '#default_value' => $width,
        '#min' => 10,
        '#max' => 100,
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * Builds the modal fieldset.
   *
   * @param int $modal_width
   *   Current modal width.
   * @param int $modal_height
   *   Current modal height.
   *
   * @return array
   *   The modal fieldset render array.
   */
  protected function buildModalFieldset(int $modal_width, int $modal_height): array {
    return [
      '#type' => 'fieldset',
      '#title' => $this->t('Detail modal'),
      'modal_width' => [
        '#type' => 'number',
        '#title' => $this->t('Width (px)'),
        '#description' => $this->t('Width of the node detail modal panel.'),
        '#default_value' => $modal_width,
        '#min' => 200,
        '#max' => 1200,
        '#required' => TRUE,
      ],
      'modal_height' => [
        '#type' => 'number',
        '#title' => $this->t('Height (px)'),
        '#description' => $this->t('Maximum height of the node detail modal panel.'),
        '#default_value' => $modal_height,
        '#min' => 200,
        '#max' => 2000,
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * Builds the zoom fieldset.
   *
   * @param bool $zoom_enabled
   *   Whether zoom is enabled.
   * @param float $zoom_min
   *   Minimum zoom scale.
   * @param float $zoom_max
   *   Maximum zoom scale.
   *
   * @return array
   *   The zoom fieldset render array.
   */
  protected function buildZoomFieldset(bool $zoom_enabled, float $zoom_min, float $zoom_max): array {
    $zoom_visible = [':input[name="zoom_enabled"]' => ['checked' => TRUE]];

    return [
      '#type' => 'fieldset',
      '#title' => $this->t('Zoom'),
      'zoom_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable zoom'),
        '#description' => $this->t('Allow users to zoom and pan the organigram.'),
        '#default_value' => $zoom_enabled,
      ],
      'zoom_min' => [
        '#type' => 'number',
        '#title' => $this->t('Minimum zoom scale'),
        '#description' => $this->t('E.g. 0.1 allows zooming out to 10% of original size.'),
        '#default_value' => $zoom_min,
        '#min' => 0.01,
        '#max' => 1.0,
        '#step' => 0.01,
        '#required' => TRUE,
        '#states' => ['visible' => $zoom_visible],
      ],
      'zoom_max' => [
        '#type' => 'number',
        '#title' => $this->t('Maximum zoom scale'),
        '#description' => $this->t('E.g. 3 allows zooming in to 300% of original size.'),
        '#default_value' => $zoom_max,
        '#min' => 1.0,
        '#max' => 20.0,
        '#step' => 0.1,
        '#required' => TRUE,
        '#states' => ['visible' => $zoom_visible],
      ],
    ];
  }

  /**
   * Builds the behaviour fieldset.
   *
   * @param int $collapse_depth
   *   Default collapse depth.
   *
   * @return array
   *   The behaviour fieldset render array.
   */
  protected function buildBehaviourFieldset(int $collapse_depth): array {
    return [
      '#type' => 'fieldset',
      '#title' => $this->t('Behaviour'),
      'collapse_depth' => [
        '#type' => 'number',
        '#title' => $this->t('Default collapse depth'),
        '#description' => $this->t(
          'Nodes deeper than this level are collapsed on initial load. Use 0 to expand all.'
        ),
        '#default_value' => $collapse_depth,
        '#min' => 0,
        '#max' => 20,
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('organigram_d3.settings')
      ->set('height', (int) $form_state->getValue('height'))
      ->set('width', (int) $form_state->getValue('width'))
      ->set('modal_height', (int) $form_state->getValue('modal_height'))
      ->set('modal_width', (int) $form_state->getValue('modal_width'))
      ->set('zoom_enabled', (bool) $form_state->getValue('zoom_enabled'))
      ->set('zoom_min', (float) $form_state->getValue('zoom_min'))
      ->set('zoom_max', (float) $form_state->getValue('zoom_max'))
      ->set('collapse_depth', (int) $form_state->getValue('collapse_depth'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
