/**
 * @file
 * organigram.js — D3 v7 organigram renderer for the Organigram node content type.
 *
 * Drupal behaviour: attaches once to #organigram-container.
 * Reads drupalSettings.organigram.dataUrl for the JSON endpoint.
 *
 * Features:
 *  - Hierarchical top-down tree (d3.tree)
 *  - Click-to-open slide-in detail modal
 *  - Vacant node visual treatment (dashed border, italic)
 *  - Collapsible subtrees (double-click)
 *  - Responsive: re-renders on window resize (debounced)
 *  - Keyboard accessible close (Escape)
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.organigram = {
    attach(context) {
      const containers = once('organigram', '#organigram-container', context);
      if (!containers.length) return;

      const container = containers[0];
      const cfg = drupalSettings.organigram || {};
      const dataUrl = cfg.dataUrl;

      if (!dataUrl) {
        console.error('[Organigram] drupalSettings.organigram.dataUrl is not set.');
        return;
      }

      // ── Fetch tree data ─────────────────────────────────────────────────
      console.log('dataUrl:', dataUrl);
      fetch(dataUrl, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => renderOrganigram(container, data))
        .catch(err => {
          console.error('[Organigram] Failed to load data:', err);
          container.innerHTML = '<p class="organigram__error">Failed to load organigram data.</p>';
        });
    },
  };

  // ── Constants ─────────────────────────────────────────────────────────────
  const NODE_W     = 160;
  const NODE_H     = 54;
  const DX         = 190;  // horizontal node spacing
  const DY         = 110;  // vertical level spacing
  const MARGIN     = { top: 36, right: 80, bottom: 40, left: 80 };
  const DEPT_PAD   = 18;   // padding around department cluster rects
  const DEPT_LABEL = 14;   // height reserved for cluster label

  // ── Main render ───────────────────────────────────────────────────────────
  function renderOrganigram(container, treeData) {
    container.innerHTML = '';

    // Wrapper for chart + modal side by side.
    const wrapper = document.createElement('div');
    wrapper.className = 'organigram__wrapper';
    container.appendChild(wrapper);

    const chartEl = document.createElement('div');
    chartEl.className = 'organigram__chart';
    wrapper.appendChild(chartEl);

    const modal = buildModal(wrapper);

    // ── D3 hierarchy ──────────────────────────────────────────────────────
    const root = d3.hierarchy(treeData);
    // Initialise collapsed nodes.
    root.each(d => {
      if (d.data.collapsed && d.children) {
        d._children = d.children;
        d.children  = null;
      }
    });

    function update() {
      // Re-layout after collapse/expand.
      d3.tree().nodeSize([DX, DY])(root);

      let x0 =  Infinity, x1 = -Infinity;
      root.each(d => { if (d.x < x0) x0 = d.x; if (d.x > x1) x1 = d.x; });

      const svgW = (x1 - x0) + NODE_W + MARGIN.left + MARGIN.right;
      const svgH = root.height * DY + NODE_H + MARGIN.top + MARGIN.bottom;
      const ox   = MARGIN.left + NODE_W / 2 - x0;
      const oy   = MARGIN.top  + NODE_H / 2;

      // Clear and rebuild SVG.
      chartEl.innerHTML = '';

      const svg = d3.select(chartEl)
        .append('svg')
        .attr('viewBox', `0 0 ${svgW} ${svgH}`)
        .style('width',  '100%')
        .style('height', 'auto')
        .style('display', 'block')
        .attr('aria-label', 'Organisational chart');

      // Inline styles using CSS custom properties so dark mode works.
      svg.append('style').text(`
        .org-link   { fill:none; stroke:var(--organigram-link-color,#ccc); stroke-width:.5; }
        .org-node   { cursor:pointer; }
        .org-rect   { fill:var(--organigram-node-bg,#fff); stroke:var(--organigram-node-border,#ccc); stroke-width:.5; transition:stroke .15s; }
        .org-rect--vacant   { stroke-dasharray:5,3; fill:var(--organigram-vacant-bg,#f9f9f7); }
        .org-rect--selected { stroke-width:2; stroke:var(--organigram-selected-border,#333); }
        .org-title  { font-size:10px; font-weight:600; font-family:system-ui,sans-serif; fill:var(--organigram-text,#1a1a18); }
        .org-person { font-size:9px;  font-family:system-ui,sans-serif; fill:var(--organigram-subtext,#777); }
        .org-vacant-label { font-size:9px; font-style:italic; font-family:system-ui,sans-serif; fill:var(--organigram-vacant-text,#aaa); }
        .org-cluster-rect  { stroke-width:.5; stroke-dasharray:5,3; }
        .org-cluster-label { font-size:9px; font-weight:600; font-family:system-ui,sans-serif; }
        .org-node:hover .org-rect { stroke:var(--organigram-hover-border,#555); }
      `);

      const g = svg.append('g').attr('transform', `translate(${ox},${oy})`);

      // ── Department cluster backgrounds ─────────────────────────────────
      drawClusters(g, root);

      // ── Links — styled from the TARGET node's organigram_node_type_settings ────────
      g.selectAll('.org-link')
        .data(root.links())
        .join('path')
        .attr('class', 'org-link')
        .attr('d', d => {
          const sx = d.source.x, sy = d.source.y + NODE_H / 2;
          const ex = d.target.x, ey = d.target.y - NODE_H / 2;
          const my = (sy + ey) / 2;
          return `M${sx},${sy}V${my}H${ex}V${ey}`;
        })
        .each(function (d) {
          const gts = d.target.data.organigram_node_type_settings;
          const sel = d3.select(this);
          if (gts) {
            sel.attr('stroke', gts.line_color)
               .attr('stroke-width', gts.line_size)
               .attr('stroke-dasharray', gts.line_dash_array === 'none' ? null : gts.line_dash_array);
          }
        });

      // ── Nodes ───────────────────────────────────────────────────────────
      const nodeG = g.selectAll('.org-node')
        .data(root.descendants())
        .join('g')
        .attr('class', 'org-node')
        .attr('transform', d => `translate(${d.x},${d.y})`)
        .attr('tabindex', 0)
        .attr('role', 'button')
        .attr('aria-label', d => d.data.vacant ? `${d.data.title} — Vacant` : `${d.data.title} — ${d.data.responsible_name || ''}`)
        .on('click', (event, d) => {
          g.selectAll('.org-rect').classed('org-rect--selected', false);
          d3.select(event.currentTarget).select('.org-rect').classed('org-rect--selected', true);
          openModal(modal, d.data);
        })
        .on('dblclick', (event, d) => {
          event.stopPropagation();
          toggleCollapse(d);
          update();
        })
        .on('keydown', (event, d) => {
          if (event.key === 'Enter' || event.key === ' ') openModal(modal, d.data);
        });

      // Node rectangle — styled from organigram_node_type_settings when available.
      nodeG.append('rect')
        .attr('class', d => `org-rect${d.data.vacant ? ' org-rect--vacant' : ''}`)
        .attr('x', -NODE_W / 2).attr('y', -NODE_H / 2)
        .attr('width', NODE_W).attr('height', NODE_H)
        .attr('rx', 7)
        .each(function (d) {
          const gts = d.data.organigram_node_type_settings;
          const sel = d3.select(this);
          if (gts) {
            if (!d.data.vacant) sel.attr('fill', gts.box_background);
            sel.attr('stroke', gts.line_color);
          }
        });

      // Text: role title (wrapped if needed) + person name or "Vacant".
      nodeG.each(function (d) {
        const el  = d3.select(this);
        const gts = d.data.organigram_node_type_settings;
        const fontColor = gts?.box_font_color ?? null;
        const fontSize  = gts?.box_font_size  ?? null;

        const role  = d.data.position_title || d.data.title;
        const hasPerson = !!d.data.responsible_name;
        let lines = [role];
        if (role.length > 22) {
          const words = role.split(' ');
          const mid   = Math.ceil(words.length / 2);
          lines = [words.slice(0, mid).join(' '), words.slice(mid).join(' ')];
        }
        const lineH = 13;
        const total = lines.length + 1; // +1 for person/vacant row
        let   y     = -(total - 1) * lineH / 2;

        lines.forEach(line => {
          const t = el.append('text').attr('class', 'org-title')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('y', y).text(line);
          if (fontColor) t.attr('fill', fontColor);
          if (fontSize)  t.attr('font-size', fontSize);
          y += lineH;
        });

        if (hasPerson) {
          const pt = el.append('text').attr('class', 'org-person')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('y', y).text(d.data.responsible_name);
          if (fontColor) pt.attr('fill', fontColor).attr('opacity', 0.72);
        }
        else {
          el.append('text').attr('class', 'org-vacant-label')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('y', y).text('Vacant');
        }

        // Collapse indicator (small chevron).
        if (d._children) {
          el.append('text')
            .attr('x', NODE_W / 2 - 10).attr('y', 4)
            .attr('font-size', 10).attr('fill', '#aaa')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .text('▸');
        }
      });
    }

    update();

    // Debounced resize.
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(update, 200);
    });
  }

  // ── Department cluster drawing ─────────────────────────────────────────────
  function drawClusters(g, root) {
    // Group nodes by organigram_node_type id (preferred).
    const groups = {};
    root.descendants().forEach(d => {
      const key = d.data.organigram_node_type;
      if (!key) return;
      if (!groups[key]) {
        const color = d.data.organigram_node_type_settings?.box_background || '#e0e0e0';
        groups[key] = { nodes: [], color };
      }
      groups[key].nodes.push(d);
    });

    Object.values(groups).forEach(({ nodes, color }) => {
      if (nodes.length < 2) return; // single node doesn't need a cluster bg

      const xs  = nodes.map(d => d.x);
      const ys  = nodes.map(d => d.y);
      const bx  = Math.min(...xs) - NODE_W / 2 - DEPT_PAD;
      const by  = Math.min(...ys) - NODE_H / 2 - DEPT_PAD - DEPT_LABEL;
      const bw  = (Math.max(...xs) - Math.min(...xs)) + NODE_W + DEPT_PAD * 2;
      const bh  = (Math.max(...ys) - Math.min(...ys)) + NODE_H + DEPT_PAD * 2 + DEPT_LABEL;

      // Derive a light tint from the hex color (mix toward white).
      const fill   = hexTint(color, 0.15); // 15% color, 85% white
      const stroke = color;

      g.insert('rect', ':first-child')
        .attr('class', 'org-cluster-rect')
        .attr('x', bx).attr('y', by)
        .attr('width', bw).attr('height', bh)
        .attr('rx', 10)
        .attr('fill', fill)
        .attr('stroke', stroke);

      // Label is the title of the top-most node in this cluster.
      const topNode = nodes.reduce((a, b) => (a.depth < b.depth ? a : b));
      g.insert('text', ':first-child')
        .attr('class', 'org-cluster-label')
        .attr('x', bx + 8).attr('y', by + DEPT_LABEL - 2)
        .attr('fill', color)
        .text(topNode.data.title);
    });
  }

  // ── Collapse/expand ────────────────────────────────────────────────────────
  function toggleCollapse(d) {
    if (d.children) {
      d._children = d.children;
      d.children  = null;
    }
    else if (d._children) {
      d.children  = d._children;
      d._children = null;
    }
  }

  // ── Modal ──────────────────────────────────────────────────────────────────
  function buildModal(wrapper) {
    const panel = document.createElement('aside');
    panel.className = 'organigram__modal';
    panel.setAttribute('aria-label', 'Role details');
    panel.setAttribute('aria-hidden', 'true');
    panel.innerHTML = '<button class="organigram__modal-close" aria-label="Close details">✕</button><div class="organigram__modal-body"></div>';
    wrapper.appendChild(panel);

    panel.querySelector('.organigram__modal-close').addEventListener('click', () => closeModal(panel));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(panel); });
    return panel;
  }

  function openModal(panel, data) {
    const body = panel.querySelector('.organigram__modal-body');
    body.innerHTML = renderModalContent(data);
    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
  }

  function closeModal(panel) {
    panel.setAttribute('aria-hidden', 'true');
    panel.classList.remove('is-open');
  }

  function renderModalContent(d) {
    const vacant = d.vacant;
    const ini    = d.responsible_name ? d.responsible_name.split(' ').map(w => w[0]).join('') : '?';
    const gts    = d.organigram_node_type_settings;
    const color  = gts?.box_background || '#888';
    const tint   = hexTint(color, 0.2);

    let html = `
      <div class="org-modal__header">
        <div class="org-modal__avatar" style="background:${tint};border-color:${color};color:${color};">${ini}</div>
        <div class="org-modal__title-wrap">
          <h2 class="org-modal__role">${escHtml(d.position_title || d.title)}</h2>
          <p class="org-modal__person ${vacant ? 'is-vacant' : ''}">${vacant ? 'Vacant' : escHtml(d.responsible_name || '')}</p>
        </div>
      </div>`;

    // Links row (CV + Declaration of Interest).
    if (!vacant && (d.cv || d.declaration_interest)) {
      html += '<div class="org-modal__links">';
      if (d.cv) {
        html += `<a href="${escHtml(d.cv)}" target="_blank" rel="noopener" class="org-modal__link">📄 CV</a>`;
      }
      if (d.declaration_interest) {
        html += `<a href="${escHtml(d.declaration_interest)}" target="_blank" rel="noopener" class="org-modal__link">📋 Declaration of Interest</a>`;
      }
      html += '</div>';
    }

    // Description & Scope of Work.
    if (d.field_scope_of_work) {
      const scopeHeading = d.field_scope_of_work_title || d.field_scope_of_works_title || 'Description & Scope of Work';
      html += '<div class="org-modal__scope">';
      html += `<h3 class="org-modal__scope-heading">${escHtml(scopeHeading)}</h3>`;
      html += `<div class="org-modal__scope-content">${d.field_scope_of_work}</div>`;
      html += '</div>';
    }

    // Related nodes (matrix/dotted lines).
    if (d.related_nodes && d.related_nodes.length > 0) {
      html += '<div class="org-modal__related"><h3>Related nodes</h3><ul>';
      d.related_nodes.forEach(rn => {
        html += `<li>${escHtml(rn.title)}</li>`;
      });
      html += '</ul></div>';
    }

    return html;
  }

  // ── Utilities ──────────────────────────────────────────────────────────────
  /**
   * Mixes a hex color toward white at the given ratio (0 = white, 1 = full color).
   */
  function hexTint(hex, ratio) {
    const clean = hex.replace('#', '');
    if (clean.length !== 6) return '#f9f9f9';
    const r = parseInt(clean.slice(0, 2), 16);
    const g = parseInt(clean.slice(2, 4), 16);
    const b = parseInt(clean.slice(4, 6), 16);
    const tr = Math.round(r * ratio + 255 * (1 - ratio));
    const tg = Math.round(g * ratio + 255 * (1 - ratio));
    const tb = Math.round(b * ratio + 255 * (1 - ratio));
    return `rgb(${tr},${tg},${tb})`;
  }

  function escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

}(Drupal, drupalSettings, once));
