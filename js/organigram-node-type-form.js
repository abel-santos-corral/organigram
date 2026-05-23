/**
 * @file
 * Updates the Organigram Node Type edit-form preview.
 */
(function (Drupal, once) {
  'use strict';

  const dashArrays = {
    solid: 'none',
    dashed: '8,4',
    dotted: '2,3',
    dashdot: '8,3,2,3',
  };

  Drupal.behaviors.organigramNodeTypeForm = {
    attach(context) {
      once('organigram-node-type-form', '#organigram-node-type-preview', context).forEach(preview => {
        const form = preview.closest('form');
        if (!form) {
          return;
        }

        const fields = {
          label: form.querySelector('[name="label"]'),
          boxFontSize: form.querySelector('[name="box_font_size"]'),
          boxFontColor: form.querySelector('[name="box_font_color"]'),
          boxBackground: form.querySelector('[name="box_background"]'),
          lineSize: form.querySelector('[name="line_size"]'),
          lineColor: form.querySelector('[name="line_color"]'),
          lineType: form.querySelector('[name="line_type"]'),
        };

        const update = () => {
          const svg = preview.querySelector('.organigram-node-type-preview');
          const box = preview.querySelector('.organigram-node-type-preview__box');
          const line = preview.querySelector('.organigram-node-type-preview__line');
          const label = preview.querySelector('.organigram-node-type-preview__label');
          const person = preview.querySelector('.organigram-node-type-preview__person');
          if (!svg || !box || !line || !label || !person) {
            return;
          }

          const boxFontSize = fields.boxFontSize?.value || '11';
          const boxFontColor = fields.boxFontColor?.value || '#1a1a18';
          const boxBackground = fields.boxBackground?.value || '#ffffff';
          const lineSize = fields.lineSize?.value || '1';
          const lineColor = fields.lineColor?.value || '#cccccc';
          const lineType = fields.lineType?.value || 'solid';
          const dashArray = dashArrays[lineType] || 'none';

          box.setAttribute('fill', boxBackground);
          box.setAttribute('stroke', lineColor);
          box.setAttribute('stroke-width', lineSize);
          line.setAttribute('stroke', lineColor);
          line.setAttribute('stroke-width', lineSize);
          if (dashArray === 'none') {
            line.removeAttribute('stroke-dasharray');
          }
          else {
            line.setAttribute('stroke-dasharray', dashArray);
          }

          label.setAttribute('font-size', boxFontSize);
          label.setAttribute('fill', boxFontColor);
          label.textContent = fields.label?.value || Drupal.t('Example node');
          person.setAttribute('fill', boxFontColor);
        };

        Object.values(fields).forEach(field => {
          field?.addEventListener('input', update);
          field?.addEventListener('change', update);
        });
        update();
      });
    },
  };
}(Drupal, once));
