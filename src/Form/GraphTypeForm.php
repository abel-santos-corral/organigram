<?php

namespace Drupal\organigram\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\organigram\Entity\GraphType;

/**
 * Add / Edit form for the Graph Type config entity.
 *
 * Renders a live preview box and line that update as the webmaster
 * adjusts the form values, so they can see exactly what a node of
 * this type will look like in the organigram before saving.
 */
class GraphTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\organigram\Entity\GraphTypeInterface $gt */
    $gt = $this->entity;

    // ── Identity ─────────────────────────────────────────────────────────────
    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Name'),
      '#description'   => $this->t('Human-readable name shown in the node type selector. Example: <em>Department</em>, <em>Squad</em>.'),
      '#default_value' => $gt->label(),
      '#required'      => TRUE,
      '#maxlength'     => 64,
    ];

    $form['id'] = [
      '#type'          => 'machine_name',
      '#default_value' => $gt->id(),
      '#maxlength'     => 32,
      '#machine_name'  => [
        'exists'    => [GraphType::class, 'load'],
        'source'    => ['label'],
      ],
      '#disabled'      => !$gt->isNew(),
    ];

    // ── Box ───────────────────────────────────────────────────────────────────
    $form['box'] = [
      '#type'        => 'details',
      '#title'       => $this->t('Box'),
      '#description' => $this->t('Visual appearance of the node rectangle in the organigram.'),
      '#open'        => TRUE,
    ];

    $form['box']['box_font_size'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Font size'),
      '#field_suffix'  => 'px',
      '#default_value' => $gt->getBoxFontSize(),
      '#min'           => 8,
      '#max'           => 32,
      '#step'          => 1,
      '#required'      => TRUE,
    ];

    $form['box']['box_font_color'] = [
      '#type'          => 'color',
      '#title'         => $this->t('Font colour'),
      '#default_value' => $gt->getBoxFontColor(),
      '#required'      => TRUE,
    ];

    $form['box']['box_background'] = [
      '#type'          => 'color',
      '#title'         => $this->t('Background colour'),
      '#default_value' => $gt->getBoxBackground(),
      '#required'      => TRUE,
    ];

    // ── Line ─────────────────────────────────────────────────────────────────
    $form['line'] = [
      '#type'        => 'details',
      '#title'       => $this->t('Connector line'),
      '#description' => $this->t('Appearance of the lines connecting nodes in the hierarchy.'),
      '#open'        => TRUE,
    ];

    $form['line']['line_size'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Width'),
      '#options'       => GraphType::lineSizeOptions(),
      '#default_value' => $gt->getLineSize(),
      '#required'      => TRUE,
    ];

    $form['line']['line_color'] = [
      '#type'          => 'color',
      '#title'         => $this->t('Colour'),
      '#default_value' => $gt->getLineColor(),
      '#required'      => TRUE,
    ];

    $form['line']['line_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Type'),
      '#options'       => GraphType::lineTypeOptions(),
      '#default_value' => $gt->getLineType(),
      '#required'      => TRUE,
    ];

    // ── Live preview ─────────────────────────────────────────────────────────
    $form['preview'] = [
      '#type'   => 'details',
      '#title'  => $this->t('Live preview'),
      '#open'   => TRUE,
    ];

    $form['preview']['canvas'] = [
      '#markup' => $this->buildPreviewMarkup($gt),
      '#prefix' => '<div id="graph-type-preview">',
      '#suffix' => '</div>',
    ];

    $form['preview']['note'] = [
      '#markup' => '<p><small>' . $this->t('Save the form to refresh this preview.') . '</small></p>',
    ];

    return $form;
  }

  /**
   * Builds a static SVG preview of a node and its connector line.
   */
  protected function buildPreviewMarkup(mixed $gt): string {
    if (!$gt instanceof \Drupal\organigram\Entity\GraphTypeInterface) {
      return '';
    }

    $bg       = htmlspecialchars($gt->getBoxBackground());
    $fc       = htmlspecialchars($gt->getBoxFontColor());
    $fs       = (int) $gt->getBoxFontSize();
    $lc       = htmlspecialchars($gt->getLineColor());
    $lw       = htmlspecialchars($gt->getLineSize());
    $da       = htmlspecialchars($gt->getLineDashArray());
    $label    = htmlspecialchars($gt->label() ?: $this->t('Example node'));

    return <<<SVG
<svg width="300" height="160" viewBox="0 0 300 160"
     xmlns="http://www.w3.org/2000/svg"
     style="border:1px solid #eee;border-radius:8px;background:#f9f9f7;display:block;margin-top:8px;">

  <!-- Parent node (greyed) -->
  <rect x="90" y="10" width="120" height="44" rx="6"
        fill="#f0f0ee" stroke="#ccc" stroke-width="0.5"/>
  <text x="150" y="37" text-anchor="middle" font-size="10"
        font-family="system-ui,sans-serif" fill="#aaa">Parent node</text>

  <!-- Connector line -->
  <line x1="150" y1="54" x2="150" y2="96"
        stroke="{$lc}" stroke-width="{$lw}" stroke-dasharray="{$da}"/>

  <!-- This graph type's node -->
  <rect x="90" y="96" width="120" height="44" rx="6"
        fill="{$bg}" stroke="{$lc}" stroke-width="{$lw}"/>
  <text x="150" y="114" text-anchor="middle" dominant-baseline="middle"
        font-size="{$fs}" font-family="system-ui,sans-serif" fill="{$fc}"
        font-weight="600">{$label}</text>
  <text x="150" y="130" text-anchor="middle" dominant-baseline="middle"
        font-size="9" font-family="system-ui,sans-serif" fill="{$fc}"
        opacity="0.65">Jane Doe</text>
</svg>
SVG;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    // Map fieldset sub-keys to entity properties before saving.
    $values = $form_state->getValues();

    $this->entity->set('box_font_size',  (int) ($values['box_font_size']  ?? $values['box']['box_font_size']  ?? 11));
    $this->entity->set('box_font_color', $values['box_font_color']  ?? $values['box']['box_font_color']  ?? '#1a1a18');
    $this->entity->set('box_background', $values['box_background']  ?? $values['box']['box_background']  ?? '#ffffff');
    $this->entity->set('line_size',      $values['line_size']       ?? $values['line']['line_size']       ?? '1');
    $this->entity->set('line_color',     $values['line_color']      ?? $values['line']['line_color']      ?? '#cccccc');
    $this->entity->set('line_type',      $values['line_type']       ?? $values['line']['line_type']       ?? 'solid');

    $status = parent::save($form, $form_state);

    $label = $this->entity->label();
    match ($status) {
      SAVED_NEW      => $this->messenger()->addStatus($this->t('Graph type %label has been created.', ['%label' => $label])),
      SAVED_UPDATED  => $this->messenger()->addStatus($this->t('Graph type %label has been updated.', ['%label' => $label])),
    };

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }

}
