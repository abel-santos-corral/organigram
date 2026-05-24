<?php

namespace Drupal\organigram\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\organigram\Entity\OrganigramNodeType;
use Drupal\organigram\Entity\OrganigramNodeTypeInterface;

/**
 * Add / Edit form for the Organigram Node Type config entity.
 *
 * Renders a live preview box and line that update as the webmaster
 * adjusts the form values, so they can see exactly what a node of
 * this type will look like in the organigram before saving.
 */
class OrganigramNodeTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\organigram\Entity\OrganigramNodeTypeInterface $node_type */
    $node_type = $this->entity;

    $form += $this->buildIdentityElements($node_type);
    $form['box'] = $this->buildBoxElement($node_type);
    $form['line'] = $this->buildLineElement($node_type);
    $form['preview'] = $this->buildPreviewElement($node_type);

    $form['#attached']['library'][] = 'organigram/node_type_form';

    return $form;
  }

  /**
   * Builds identity form elements.
   */
  protected function buildIdentityElements(
    OrganigramNodeTypeInterface $node_type,
  ): array {
    return [
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#description' => $this->t('Human-readable name shown in the node type selector. Example: <em>Department</em>, <em>Squad</em>.'),
        '#default_value' => $node_type->label(),
        '#required' => TRUE,
        '#maxlength' => 64,
      ],
      'id' => [
        '#type' => 'machine_name',
        '#default_value' => $node_type->id(),
        '#maxlength' => 32,
        '#machine_name' => [
          'exists' => [OrganigramNodeType::class, 'load'],
          'source' => ['label'],
        ],
        '#disabled' => !$node_type->isNew(),
      ],
    ];
  }

  /**
   * Builds box style form elements.
   */
  protected function buildBoxElement(OrganigramNodeTypeInterface $node_type): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Box'),
      '#description' => $this->t('Visual appearance of the node rectangle in the organigram.'),
      '#open' => TRUE,
      'box_font_size' => [
        '#type' => 'number',
        '#title' => $this->t('Font size'),
        '#field_suffix' => 'px',
        '#default_value' => $node_type->getBoxFontSize(),
        '#min' => 8,
        '#max' => 32,
        '#step' => 1,
        '#required' => TRUE,
      ],
      'box_font_color' => [
        '#type' => 'color',
        '#title' => $this->t('Font colour'),
        '#default_value' => $node_type->getBoxFontColor(),
        '#required' => TRUE,
      ],
      'box_background' => [
        '#type' => 'color',
        '#title' => $this->t('Background colour'),
        '#default_value' => $node_type->getBoxBackground(),
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * Builds line style form elements.
   */
  protected function buildLineElement(
    OrganigramNodeTypeInterface $node_type,
  ): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Connector line'),
      '#description' => $this->t('Appearance of the lines connecting nodes in the hierarchy.'),
      '#open' => TRUE,
      'line_size' => [
        '#type' => 'select',
        '#title' => $this->t('Width'),
        '#options' => $this->lineSizeOptions(),
        '#default_value' => $node_type->getLineSize(),
        '#required' => TRUE,
      ],
      'line_color' => [
        '#type' => 'color',
        '#title' => $this->t('Colour'),
        '#default_value' => $node_type->getLineColor(),
        '#required' => TRUE,
      ],
      'line_type' => [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#options' => $this->lineTypeOptions(),
        '#default_value' => $node_type->getLineType(),
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * Builds the live preview form element.
   */
  protected function buildPreviewElement(
    OrganigramNodeTypeInterface $node_type,
  ): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Live preview'),
      '#open' => TRUE,
      'canvas' => [
        '#type' => 'inline_template',
        '#template' => $this->buildPreviewMarkup($node_type),
        '#prefix' => '<div id="organigram-node-type-preview">',
        '#suffix' => '</div>',
      ],
      'note' => [
        '#markup' => '<p><small>' . $this->t('The preview updates as the form values change.') . '</small></p>',
      ],
    ];
  }

  /**
   * Builds a static SVG preview of a node and its connector line.
   */
  protected function buildPreviewMarkup(mixed $node_type): string {
    if (!$node_type instanceof OrganigramNodeTypeInterface) {
      return '';
    }

    $background = $this->escapeMarkup($node_type->getBoxBackground());
    $font_color = $this->escapeMarkup($node_type->getBoxFontColor());
    $font_size = (int) $node_type->getBoxFontSize();
    $line_color = $this->escapeMarkup($node_type->getLineColor());
    $line_width = $this->escapeMarkup($node_type->getLineSize());
    $dash_array = $this->escapeMarkup($node_type->getLineDashArray());
    $label = $this->escapeMarkup($node_type->label() ?: $this->t('Example node'));

    return <<<SVG
<svg class="organigram-node-type-preview" width="300" height="160" viewBox="0 0 300 160"
     xmlns="http://www.w3.org/2000/svg"
     style="border:1px solid #eee;border-radius:8px;background:#f9f9f7;display:block;margin-top:8px;">

  <!-- Parent node (greyed) -->
  <rect x="90" y="10" width="120" height="44" rx="6"
        fill="#f0f0ee" stroke="#ccc" stroke-width="0.5"/>
  <text x="150" y="37" text-anchor="middle" font-size="10"
        font-family="system-ui,sans-serif" fill="#aaa">Parent node</text>

  <!-- Connector line -->
  <line class="organigram-node-type-preview__line" x1="150" y1="54" x2="150" y2="96"
        stroke="{$line_color}" stroke-width="{$line_width}" stroke-dasharray="{$dash_array}"/>

  <!-- This Organigram Node Type's node -->
  <rect class="organigram-node-type-preview__box" x="90" y="96" width="120" height="44" rx="6"
        fill="{$background}" stroke="{$line_color}" stroke-width="{$line_width}"/>
  <text class="organigram-node-type-preview__label" x="150" y="114" text-anchor="middle" dominant-baseline="middle"
        font-size="{$font_size}" font-family="system-ui,sans-serif" fill="{$font_color}"
        font-weight="600">{$label}</text>
  <text class="organigram-node-type-preview__person" x="150" y="130" text-anchor="middle" dominant-baseline="middle"
        font-size="9" font-family="system-ui,sans-serif" fill="{$font_color}"
        opacity="0.65">Jane Doe</text>
</svg>
SVG;
  }

  /**
   * Returns escaped text for preview SVG attributes and text nodes.
   */
  protected function escapeMarkup(mixed $value): string {
    return htmlspecialchars(
      (string) $value,
      ENT_QUOTES | ENT_SUBSTITUTE,
      'UTF-8'
    );
  }

  /**
   * Returns the allowed line size options.
   */
  protected function lineSizeOptions(): array {
    return [
      '0.5' => '0.5 px',
      '1' => '1 px',
      '1.5' => '1.5 px',
      '2' => '2 px',
    ];
  }

  /**
   * Returns the allowed line type options.
   */
  protected function lineTypeOptions(): array {
    return [
      'solid' => $this->t('Solid'),
      'dashed' => $this->t('Dashed'),
      'dotted' => $this->t('Dotted'),
      'dashdot' => $this->t('Dash-dot'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    // Map fieldset sub-keys to entity properties before saving.
    $values = $form_state->getValues();

    $this->entity->set('box_font_size', (int) ($values['box_font_size'] ?? $values['box']['box_font_size'] ?? 11));
    $this->entity->set('box_font_color', $values['box_font_color'] ?? $values['box']['box_font_color'] ?? '#1a1a18');
    $this->entity->set('box_background', $values['box_background'] ?? $values['box']['box_background'] ?? '#ffffff');
    $this->entity->set('line_size', $values['line_size'] ?? $values['line']['line_size'] ?? '1');
    $this->entity->set('line_color', $values['line_color'] ?? $values['line']['line_color'] ?? '#cccccc');
    $this->entity->set('line_type', $values['line_type'] ?? $values['line']['line_type'] ?? 'solid');

    $status = parent::save($form, $form_state);

    $label = $this->entity->label();
    match ($status) {
      SAVED_NEW      => $this->messenger()->addStatus($this->t('Organigram Node Type %label has been created.', ['%label' => $label])),
      SAVED_UPDATED  => $this->messenger()->addStatus($this->t('Organigram Node Type %label has been updated.', ['%label' => $label])),
    };

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }

}
