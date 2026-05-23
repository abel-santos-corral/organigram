<?php

namespace Drupal\organigram;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Renders the admin list of Organigram Node Type config entities.
 *
 * Accessible at /admin/structure/organigram-node-type.
 */
class OrganigramNodeTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'label'      => $this->t('Name'),
      'id'         => $this->t('Machine name'),
      'box'        => $this->t('Box style'),
      'line'       => $this->t('Line style'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\organigram\Entity\OrganigramNodeTypeInterface $entity */

    $box_preview = sprintf(
      '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:%dpx;color:%s;background:%s;border:1px solid #ccc;">%s</span>',
      $entity->getBoxFontSize(),
      $entity->getBoxFontColor(),
      $entity->getBoxBackground(),
      $this->t('Aa'),
    );

    $line_preview = sprintf(
      '<span style="display:inline-flex;align-items:center;gap:6px;">'
        . '<svg width="40" height="10" style="overflow:visible">'
        . '<line x1="0" y1="5" x2="40" y2="5" stroke="%s" stroke-width="%s" stroke-dasharray="%s"/>'
        . '</svg>'
        . '<small>%s / %s px / %s</small>'
        . '</span>',
      $entity->getLineColor(),
      $entity->getLineSize(),
      $entity->getLineDashArray(),
      $entity->getLineType(),
      $entity->getLineSize(),
      $entity->getLineColor(),
    );

    return [
      'label'  => $entity->label(),
      'id'     => $entity->id(),
      'box'    => ['data' => ['#markup' => $box_preview]],
      'line'   => ['data' => ['#markup' => $line_preview]],
    ] + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t(
      'No Organigram Node Types defined yet. <a href=":url">Add a Organigram Node Type</a>.',
      [':url' => '/admin/structure/organigram-node-type/add'],
    );
    return $build;
  }

}
