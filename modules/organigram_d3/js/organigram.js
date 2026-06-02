/**
 * @file
 * organigram.js — D3 v7 organigram renderer for the Organigram node content type.
 *
 * Drupal behaviour: attaches once to #organigram-container.
 * Reads drupalSettings.organigram.graph for the pre-built graph contract and
 * drupalSettings.organigram.config for renderer settings (dimensions, zoom,
 * collapse depth) configured at /admin/config/organigram/d3.
 *
 * Accessibility: WCAG 2.2 AAA target.
 *  - Each node exposes two explicit buttons in a bottom bar:
 *    · Expand / Collapse  (left,  id="org-btn-expand-{id}")  — only if node has children
 *    · See more / See less (right, id="org-btn-detail-{id}") — only if enable_modal is true
 *  - Both buttons meet the 44 × 44 px touch-target minimum (WCAG 2.5.5 AAA).
 *  - Full keyboard navigation (Tab / Shift-Tab / Enter / Space / Escape).
 *  - Focus is trapped inside the modal while open and returned to the
 *    triggering button on close (WCAG 2.4.3, 2.4.11).
 *  - aria-expanded, aria-haspopup="dialog", role="dialog", aria-modal,
 *    aria-labelledby, role="group" on node groups.
 *  - No double-click interactions.
 *
 * Legend:
 *  - Drawn at bottom-left of the SVG, outside the zoom layer (stays fixed).
 *  - One entry per OrganigramNodeType, sorted alphabetically.
 *  - Title is passed from Drupal (drupalSettings.organigram.legendTitle) so
 *    it can be translated via Drupal's i18n system.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.organigram = {
    attach(context) {
      const containers = once('organigram', '#organigram-container', context);
      if (!containers.length) return;

      const container   = containers[0];
      const cfg         = drupalSettings.organigram || {};
      const data        = cfg.graph;
      const legendTitle = cfg.legendTitle || 'Legend';
      const rcfg        = cfg.config || {};

      // Apply renderer config to overridable CSS custom properties.
      if (rcfg.height)      { container.style.minHeight = rcfg.height + 'px'; }
      if (rcfg.width)       { container.style.width = rcfg.width + '%'; }
      if (rcfg.modalWidth)  { container.style.setProperty('--organigram-modal-width', rcfg.modalWidth + 'px'); }
      if (rcfg.modalHeight) { container.style.setProperty('--organigram-modal-max-h-tablet', rcfg.modalHeight + 'px'); }

      if (!data) {
        console.error('[Organigram] drupalSettings.organigram.graph is not set.');
        return;
      }

      const visuals = data.visuals || {};
      renderOrganigram(container, buildHierarchy(data), visuals, legendTitle, rcfg);
    },
  };

  // ── Constants ─────────────────────────────────────────────────────────────
  const NODE_W      = 200;
  const NODE_H      = 98;
  const BTN_H       = 44;
  const CONTENT_H   = NODE_H - BTN_H;
  const BTN_TOP_Y   = NODE_H / 2 - BTN_H;
  const CONTENT_MID = -(NODE_H / 2 - CONTENT_H / 2);
  const BTN_MID_Y   = BTN_TOP_Y + BTN_H / 2;
  const CORNER_R    = 7;
  const DX          = 230;
  const DY          = 140;
  const MARGIN      = { top: 36, right: 80, bottom: 40, left: 80 };
  const DEPT_PAD    = 18;
  const DEPT_LABEL  = 14;

  // Dynamic legend sizing.
  const LEGEND_PAD_X        = 18;
  const LEGEND_PAD_Y        = 16;
  const LEGEND_ITEM_GAP_X   = 18;
  const LEGEND_ITEM_GAP_Y   = 12;
  const LEGEND_SQUARE       = 14;

  // ── buildHierarchy ────────────────────────────────────────────────────────
  function buildHierarchy(contract) {
    const nodes       = contract.graph.nodes;
    const edges       = contract.graph.edges;
    const visuals     = contract.visuals || {};
    const childrenMap = {};

    Object.values(nodes).forEach(n => { childrenMap[n.id] = []; });
    edges.forEach(e => {
      if (e.type !== 'hierarchical') return;
      childrenMap[e.source].push(e.target);
    });

    function buildNode(nodeId) {
      const node   = nodes[nodeId];
      const visual = visuals[node.type] || {};
      const data   = {
        id:    node.id,
        title: node.title,
        organigram_node_type: node.type,
        organigram_node_type_settings: {
          id:             visual.id,
          label:          visual.label,
          box_background: visual.palette?.background,
          box_font_color: visual.palette?.foreground,
          line_color:     visual.palette?.border,
          line_size:      visual.border?.width,
          line_type:      visual.border?.style,
        },
        ...node.data,
      };
      data.children = childrenMap[nodeId].map(cid => buildNode(cid));
      return data;
    }

    return buildNode(contract.meta.root);
  }

  // ── SVG helper: path for a rect with per-corner radii ─────────────────────
  function roundedRectPath(x, y, w, h, tl, tr, br, bl) {
    const r   = CORNER_R;
    const _tl = tl ? r : 0, _tr = tr ? r : 0;
    const _br = br ? r : 0, _bl = bl ? r : 0;
    const x2  = x + w, y2 = y + h;
    return `M${x + _tl},${y}` +
      `H${x2 - _tr}Q${x2},${y} ${x2},${y + _tr}` +
      `V${y2 - _br}Q${x2},${y2} ${x2 - _br},${y2}` +
      `H${x + _bl}Q${x},${y2} ${x},${y2 - _bl}` +
      `V${y + _tl}Q${x},${y} ${x + _tl},${y}Z`;
  }

  // ── Main render ───────────────────────────────────────────────────────────
  function renderOrganigram(container, treeData, visuals, legendTitle, rcfg) {
    rcfg = rcfg || {};
    container.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'organigram__wrapper';
    container.appendChild(wrapper);

    const chartEl = document.createElement('div');
    chartEl.className = 'organigram__chart';
    wrapper.appendChild(chartEl);

    let currentModalNodeId = null;
    let zoomTransform      = d3.zoomIdentity;

    // ── Modal close/open helpers ──────────────────────────────────────────
    function closeModalAndReturn() {
      const prevId = currentModalNodeId;
      currentModalNodeId = null;
      closeModal(modal);

      if (prevId !== null) {
        const nodeEl = document.getElementById(`org-node-${prevId}`);
        if (nodeEl) nodeEl.querySelector('.org-rect')?.classList.remove('org-rect--selected');

        const btn = document.getElementById(`org-btn-detail-${prevId}`);
        if (btn) {
          const lbl = btn.querySelector('.org-btn__label');
          if (lbl) lbl.textContent = 'See more';
          btn.setAttribute('aria-expanded', 'false');
          btn.setAttribute('aria-label', `Show details: ${btn.dataset.nodeTitle}`);
          btn.focus();
        }
      }
    }

    function openModalForNode(d) {
      if (currentModalNodeId !== null && currentModalNodeId !== d.data.id) {
        const prevBtn  = document.getElementById(`org-btn-detail-${currentModalNodeId}`);
        const prevNode = document.getElementById(`org-node-${currentModalNodeId}`);
        if (prevBtn) {
          const lbl = prevBtn.querySelector('.org-btn__label');
          if (lbl) lbl.textContent = 'See more';
          prevBtn.setAttribute('aria-expanded', 'false');
        }
        if (prevNode) prevNode.querySelector('.org-rect')?.classList.remove('org-rect--selected');
      }

      currentModalNodeId = d.data.id;
      openModal(modal, d.data);

      const nodeEl = document.getElementById(`org-node-${d.data.id}`);
      if (nodeEl) nodeEl.querySelector('.org-rect')?.classList.add('org-rect--selected');

      const btn = document.getElementById(`org-btn-detail-${d.data.id}`);
      if (btn) {
        const lbl = btn.querySelector('.org-btn__label');
        if (lbl) lbl.textContent = 'See less';
        btn.setAttribute('aria-expanded', 'true');
        btn.setAttribute('aria-label', `Hide details: ${d.data.title}`);
      }

      modal.querySelector('.organigram__modal-close')?.focus();
    }

    const modal = buildModal(wrapper, closeModalAndReturn);

    // ── D3 hierarchy ──────────────────────────────────────────────────────
    const root = d3.hierarchy(treeData);

    root.each(d => {
      if (d.data.collapsed && d.children) {
        d._children = d.children;
        d.children  = null;
      }
    });

    // ── update() ─────────────────────────────────────────────────────────
    function update() {
      d3.tree().nodeSize([DX, DY])(root);

      let x0 = Infinity, x1 = -Infinity;
      root.each(d => {
        if (d.x < x0) x0 = d.x;
        if (d.x > x1) x1 = d.x;
      });

      let svgW = (x1 - x0) + NODE_W + MARGIN.left + MARGIN.right;

      // Legend is now an HTML overlay — no SVG space reservation needed.
      const hasLegend = Object.keys(visuals).length > 0;

      const svgH = root.height * DY + NODE_H + MARGIN.top + MARGIN.bottom;
      const ox = MARGIN.left + NODE_W / 2 - x0;
      const oy = MARGIN.top  + NODE_H / 2;

      chartEl.innerHTML = '';

      const svg = d3.select(chartEl)
        .append('svg')
        .attr('viewBox', `0 0 ${svgW} ${svgH}`)
        .style('width',  '100%')
        .style('height', `${svgH}px`)
        .style('max-width', '100%')
        .style('display', 'block')
        .attr('aria-label', 'Organisational chart');

      const zoomLayer = svg.append('g').attr('class', 'organigram__zoom-layer');
      const zoomMin = rcfg.zoomMin || 0.4;
      const zoomMax = rcfg.zoomMax || 3;
      const zoom = d3.zoom()
        .scaleExtent([zoomMin, zoomMax])
        .on('zoom', event => {
          zoomTransform = event.transform;
          zoomLayer.attr('transform', zoomTransform);
        });

      if (rcfg.zoomEnabled !== false) {
        svg.call(zoom).on('dblclick.zoom', null);
      }

      // ── Inline SVG styles ──────────────────────────────────────────────
      svg.append('style').text(`
        .org-link            { fill:none; stroke:var(--organigram-link-color,#ccc); stroke-width:var(--organigram-link-width,.5); stroke-dasharray:var(--organigram-link-dasharray,none); }
        .org-rect            { fill:var(--organigram-node-bg,#fff); stroke:var(--organigram-node-border,#ccc); stroke-width:var(--organigram-node-border-width,.5); }
        .org-rect--selected  { stroke-width:2; stroke:var(--organigram-selected-border,#333); }
        .org-title           { font-size:var(--organigram-node-font-size,10px); font-weight:600; font-family:system-ui,sans-serif; fill:var(--organigram-node-font-color,#1a1a18); pointer-events:none; }
        .org-person          { font-size:9px; font-family:system-ui,sans-serif; fill:var(--organigram-subtext,#777); pointer-events:none; }
        .org-vacant-label    { font-size:9px; font-style:italic; font-family:system-ui,sans-serif; fill:var(--organigram-vacant-text,#aaa); pointer-events:none; }
        .org-btn-divider     { fill:none; stroke:var(--organigram-node-border,#ccc); stroke-width:.5; pointer-events:none; }
        .org-btn             { cursor:pointer; }
        .org-btn__bg         { fill:transparent; transition:fill .12s; }
        .org-btn:hover .org-btn__bg,
        .org-btn:focus-visible .org-btn__bg  { fill:rgba(0,0,0,.07); }
        .org-btn:focus-visible               { outline:none; }
        .org-btn:focus-visible .org-btn__bg  { stroke:var(--organigram-focus-ring,#0055cc); stroke-width:2; }
        .org-btn__label      { font-size:9px; font-weight:600; font-family:system-ui,sans-serif; pointer-events:none; text-decoration:underline; text-underline-offset:2px; }
        .org-btn--expand .org-btn__label  { fill:var(--organigram-expand-label,#333); }
        .org-btn--detail .org-btn__label  { fill:var(--organigram-detail-label,#1a5cb5); }
        .org-cluster-rect    { stroke-width:.5; stroke-dasharray:5,3; }
        .org-cluster-label   { font-size:9px; font-weight:600; font-family:system-ui,sans-serif; }
      `);

      const g = zoomLayer.append('g').attr('transform', `translate(${ox},${oy})`);
      svg.call(zoom.transform, zoomTransform);
      buildZoomControls(chartEl, svg, zoom);

      drawClusters(g, root);

      // ── Links ──────────────────────────────────────────────────────────
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
            sel.style('--organigram-link-color',     gts.line_color)
               .style('--organigram-link-width',     gts.line_size)
               .style('--organigram-link-dasharray', gts.line_dash_array === 'none' ? 'none' : gts.line_dash_array);
          }
        });

      // ── Node groups ────────────────────────────────────────────────────
      const nodeG = g.selectAll('.org-node')
        .data(root.descendants())
        .join('g')
        .attr('class', 'org-node')
        .attr('id',    d => `org-node-${d.data.id}`)
        .attr('role',  'group')
        .attr('aria-label', d => d.data.vacant
          ? `${d.data.title} — Vacant`
          : `${d.data.title}${d.data.responsible_name ? ' — ' + d.data.responsible_name : ''}`
        )
        .attr('transform', d => `translate(${d.x},${d.y})`);

      // Background rect
      nodeG.append('rect')
        .attr('class', 'org-rect')
        .attr('x', -NODE_W / 2).attr('y', -NODE_H / 2)
        .attr('width', NODE_W).attr('height', NODE_H)
        .attr('rx', CORNER_R)
        .each(function (d) {
          const gts = d.data.organigram_node_type_settings;
          const sel = d3.select(this);
          if (gts) {
            sel.style('--organigram-node-bg',           gts.box_background)
               .style('--organigram-node-border',       gts.line_color)
               .style('--organigram-node-border-width', gts.line_size);
          }
        });

      // Text content
      nodeG.each(function (d) {
        const el        = d3.select(this);
        const gts       = d.data.organigram_node_type_settings;
        const fontColor = gts?.box_font_color ?? null;
        const fontSize  = gts?.box_font_size  ?? null;

        const role      = d.data.position_title || d.data.title;
        const hasPerson = !!d.data.responsible_name;

        let lines = [role];
        if (role.length > 26) {
          const words = role.split(' ');
          const mid   = Math.ceil(words.length / 2);
          lines = [words.slice(0, mid).join(' '), words.slice(mid).join(' ')];
        }

        const lineH = 13;
        const total = lines.length + 1;
        let   y     = CONTENT_MID - (total - 1) * lineH / 2;

        lines.forEach(line => {
          const t = el.append('text').attr('class', 'org-title')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('y', y).text(line);
          if (fontColor) t.style('--organigram-node-font-color', fontColor);
          if (fontSize)  t.style('--organigram-node-font-size',  `${fontSize}px`);
          y += lineH;
        });

        if (hasPerson) {
          const pt = el.append('text').attr('class', 'org-person')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('y', y).text(d.data.responsible_name);
          if (fontColor) pt.style('--organigram-node-font-color', fontColor).attr('opacity', 0.72);
        }
        else {
          el.append('text').attr('class', 'org-vacant-label')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('y', y).text('Vacant');
        }
      });

      // ── Button bar ─────────────────────────────────────────────────────
      nodeG.each(function (d) {
        const el          = d3.select(this);
        const hasChildren = !!(d.children || d._children);
        const showDetail  = d.data.enable_modal !== false;
        const showExpand  = hasChildren;

        if (!showExpand && !showDetail) return;

        el.append('line')
          .attr('class', 'org-btn-divider')
          .attr('x1', -NODE_W / 2 + CORNER_R)
          .attr('x2',  NODE_W / 2 - CORNER_R)
          .attr('y1', BTN_TOP_Y)
          .attr('y2', BTN_TOP_Y);

        const hasBoth = showExpand && showDetail;
        const btnW    = hasBoth ? NODE_W / 2 : NODE_W;

        // Expand / Collapse (left)
        if (showExpand) {
          const isExpanded = !!d.children;
          const bx         = -NODE_W / 2;

          const expandG = el.append('g')
            .attr('id',            `org-btn-expand-${d.data.id}`)
            .attr('class',         'org-btn org-btn--expand')
            .attr('role',          'button')
            .attr('tabindex',      0)
            .attr('aria-expanded', String(isExpanded))
            .attr('aria-label',    `${isExpanded ? 'Collapse' : 'Expand'}: ${d.data.title}`);

          expandG.append('path')
            .attr('class', 'org-btn__bg')
            .attr('d', hasBoth
              ? roundedRectPath(bx, BTN_TOP_Y, btnW, BTN_H, false, false, false, true)
              : roundedRectPath(bx, BTN_TOP_Y, btnW, BTN_H, false, false, true,  true)
            );

          expandG.append('text')
            .attr('class', 'org-btn__label')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('x', bx + btnW / 2).attr('y', BTN_MID_Y)
            .text(isExpanded ? 'Collapse' : 'Expand');

          expandG
            .on('click', event => {
              event.stopPropagation();
              toggleCollapse(d);
              update();
            })
            .on('keydown', event => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopPropagation();
                toggleCollapse(d);
                update();
              }
            });
        }

        // See more / See less (right)
        if (showDetail) {
          const bx     = hasBoth ? 0 : -NODE_W / 2;
          const isOpen = currentModalNodeId === d.data.id;

          const detailG = el.append('g')
            .attr('id',              `org-btn-detail-${d.data.id}`)
            .attr('class',           'org-btn org-btn--detail')
            .attr('role',            'button')
            .attr('tabindex',        0)
            .attr('aria-haspopup',   'dialog')
            .attr('aria-expanded',   String(isOpen))
            .attr('aria-label',      `${isOpen ? 'Hide' : 'Show'} details: ${d.data.title}`)
            .attr('data-node-title', d.data.title);

          detailG.append('path')
            .attr('class', 'org-btn__bg')
            .attr('d', hasBoth
              ? roundedRectPath(bx, BTN_TOP_Y, btnW, BTN_H, false, false, true,  false)
              : roundedRectPath(bx, BTN_TOP_Y, btnW, BTN_H, false, false, true,  true)
            );

          detailG.append('text')
            .attr('class', 'org-btn__label')
            .attr('text-anchor', 'middle').attr('dominant-baseline', 'middle')
            .attr('x', bx + btnW / 2).attr('y', BTN_MID_Y)
            .text(isOpen ? 'See less' : 'See more');

          detailG
            .on('click', event => {
              event.stopPropagation();
              currentModalNodeId === d.data.id ? closeModalAndReturn() : openModalForNode(d);
            })
            .on('keydown', event => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopPropagation();
                currentModalNodeId === d.data.id ? closeModalAndReturn() : openModalForNode(d);
              }
            });
        }
      });

      // ── Legend (HTML overlay, bottom-left of wrapper) ─────────────────
      if (hasLegend) {
        drawLegend(container, visuals, legendTitle);
      }

    } // end update()

    update();

    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(update, 200);
    });
  }

  // ── Legend ─────────────────────────────────────────────────────────────────
  /**
   * Draws a fixed legend at the bottom-left of the SVG, outside the zoom layer.
   *
   * @param {d3.Selection} svg         - The root SVG selection.
   * @param {object}       visuals     - The visuals map from the graph contract.
   * @param {number}       svgW        - Total SVG viewBox width.
   * @param {number}       svgH        - Total SVG viewBox height.
   * @param {string}       title       - Translated legend title.
   */

function drawLegend(container, visuals, title) {
    // Sort entries alphabetically by label.
    const entries = Object.values(visuals)
      .filter(v => v.label)
      .sort((a, b) => a.label.localeCompare(b.label));

    if (!entries.length) return;

    // Remove any stale legend from a previous render.
    const wrapper = container.closest('.organigram__wrapper') || container;
    const existing = wrapper.querySelector('.organigram__legend');
    if (existing) existing.remove();

    const legend = document.createElement('div');
    legend.className = 'organigram__legend';
    legend.setAttribute('role', 'img');
    legend.setAttribute('aria-label', title);

    // Title row.
    const titleEl = document.createElement('div');
    titleEl.className = 'organigram__legend-title';
    titleEl.textContent = title;
    legend.appendChild(titleEl);

    // Items row.
    const itemsEl = document.createElement('div');
    itemsEl.className = 'organigram__legend-items';

    entries.forEach(entry => {
      const item = document.createElement('div');
      item.className = 'organigram__legend-item';

      const borderStyle = entry.border?.style || 'solid';
      const dashArray   = borderStyle === 'dashed' ? '5,3'
                        : borderStyle === 'dotted' ? '2,2'
                        : null;

      // Inline SVG swatch preserves dashed/dotted border fidelity.
      const swatchSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      swatchSvg.setAttribute('width', '22');
      swatchSvg.setAttribute('height', '22');
      swatchSvg.setAttribute('aria-hidden', 'true');
      swatchSvg.classList.add('organigram__legend-swatch');

      const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      rect.setAttribute('x', '1');
      rect.setAttribute('y', '1');
      rect.setAttribute('width', '20');
      rect.setAttribute('height', '20');
      rect.setAttribute('rx', '3');
      rect.setAttribute('fill',         entry.palette?.background || '#fff');
      rect.setAttribute('stroke',       entry.palette?.border     || '#ccc');
      rect.setAttribute('stroke-width', entry.border?.width       || 1);
      if (dashArray) rect.setAttribute('stroke-dasharray', dashArray);

      swatchSvg.appendChild(rect);

      const label = document.createElement('span');
      label.className = 'organigram__legend-label';
      label.textContent = entry.label;

      item.appendChild(swatchSvg);
      item.appendChild(label);
      itemsEl.appendChild(item);
    });

    legend.appendChild(itemsEl);

    // Append to the wrapper so absolute positioning is relative to it.
    // The wrapper already carries position:relative in the stylesheet.
    wrapper.appendChild(legend);
  }

  // ── Zoom controls ─────────────────────────────────────────────────────────
  function buildZoomControls(chartEl, svg, zoom) {
    const controls = document.createElement('div');
    controls.className = 'organigram__zoom-controls';
    controls.innerHTML = [
      '<button type="button" class="organigram__zoom-button" data-zoom="in"    aria-label="Zoom in">+</button>',
      '<button type="button" class="organigram__zoom-button" data-zoom="out"   aria-label="Zoom out">-</button>',
      '<button type="button" class="organigram__zoom-button" data-zoom="reset" aria-label="Reset zoom">Reset</button>',
    ].join('');
    chartEl.appendChild(controls);

    controls.addEventListener('click', event => {
      const button = event.target.closest('button[data-zoom]');
      if (!button) return;
      if      (button.dataset.zoom === 'in')  svg.transition().duration(180).call(zoom.scaleBy, 1.25);
      else if (button.dataset.zoom === 'out') svg.transition().duration(180).call(zoom.scaleBy, 0.8);
      else                                    svg.transition().duration(180).call(zoom.transform, d3.zoomIdentity);
    });
  }

  // ── Department cluster drawing ─────────────────────────────────────────────
  function drawClusters(g, root) {
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
      if (nodes.length < 2) return;

      const xs  = nodes.map(d => d.x);
      const ys  = nodes.map(d => d.y);
      const bx  = Math.min(...xs) - NODE_W / 2 - DEPT_PAD;
      const by  = Math.min(...ys) - NODE_H / 2 - DEPT_PAD - DEPT_LABEL;
      const bw  = (Math.max(...xs) - Math.min(...xs)) + NODE_W + DEPT_PAD * 2;
      const bh  = (Math.max(...ys) - Math.min(...ys)) + NODE_H + DEPT_PAD * 2 + DEPT_LABEL;

      const fill   = hexTint(color, 0.15);
      const stroke = color;

      g.insert('rect', ':first-child')
        .attr('class', 'org-cluster-rect')
        .attr('x', bx).attr('y', by)
        .attr('width', bw).attr('height', bh)
        .attr('rx', 10)
        .attr('fill', fill)
        .attr('stroke', stroke);

      const topNode = nodes.reduce((a, b) => (a.depth < b.depth ? a : b));
      g.insert('text', ':first-child')
        .attr('class', 'org-cluster-label')
        .attr('x', bx + 8).attr('y', by + DEPT_LABEL - 2)
        .attr('fill', color)
        .text(topNode.data.title);
    });
  }

  // ── Collapse / expand ──────────────────────────────────────────────────────
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
  function buildModal(wrapper, onClose) {
    const panel = document.createElement('aside');
    panel.className = 'organigram__modal';
    panel.setAttribute('role',        'dialog');
    panel.setAttribute('aria-modal',  'true');
    panel.setAttribute('aria-label',  'Role details');
    panel.setAttribute('aria-hidden', 'true');
    panel.innerHTML =
      '<button class="organigram__modal-close" aria-label="Close details">✕</button>' +
      '<div class="organigram__modal-body"></div>';
    wrapper.appendChild(panel);

    panel.querySelector('.organigram__modal-close').addEventListener('click', onClose);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') onClose(); });

    // Focus trap.
    panel.addEventListener('keydown', e => {
      if (e.key !== 'Tab') return;
      const focusable = Array.from(panel.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      )).filter(el => !el.disabled);
      if (!focusable.length) return;
      const first = focusable[0];
      const last  = focusable[focusable.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      }
      else {
        if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
      }
    });

    return panel;
  }

  function openModal(panel, data) {
    const body = panel.querySelector('.organigram__modal-body');
    body.innerHTML = renderModalContent(data);

    const h2 = body.querySelector('.org-modal__role');
    if (h2) {
      h2.id = 'org-modal-title';
      panel.setAttribute('aria-labelledby', 'org-modal-title');
    }

    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
  }

  function closeModal(panel) {
    panel.setAttribute('aria-hidden', 'true');
    panel.classList.remove('is-open');
  }

  function renderModalContent(d) {
    const vacant = d.vacant;
    const photo  = d.responsible_photo
      ? `<img class="org-modal__photo" src="${escHtml(d.responsible_photo)}" alt="${escHtml(d.responsible_name || '')}">`
      : '';

    let html = `
      <div class="org-modal__header">
        ${photo}
        <div class="org-modal__title-wrap">
          <h2 class="org-modal__role">${escHtml(d.position_title || d.title)}</h2>
          <p class="org-modal__person ${vacant ? 'is-vacant' : ''}">
            ${vacant ? 'Vacant' : escHtml(d.responsible_name || '')}
          </p>
        </div>
      </div>`;

    if (!vacant && (d.cv || d.declaration_interest)) {
      html += '<div class="org-modal__links">';
      if (d.cv)                   html += `<a href="${escHtml(d.cv)}" target="_blank" rel="noopener" class="org-modal__link">📄 CV</a>`;
      if (d.declaration_interest) html += `<a href="${escHtml(d.declaration_interest)}" target="_blank" rel="noopener" class="org-modal__link">📋 Declaration of Interest</a>`;
      html += '</div>';
    }

    if (d.field_scope_of_work) {
      const heading = d.field_scope_of_work_title || d.field_scope_of_works_title || 'Description & Scope of Work';
      html += `<div class="org-modal__scope">
        <h3 class="org-modal__scope-heading">${escHtml(heading)}</h3>
        <div class="org-modal__scope-content">${d.field_scope_of_work}</div>
      </div>`;
    }

    if (d.related_nodes && d.related_nodes.length > 0) {
      html += '<div class="org-modal__related"><h3>Related nodes</h3><ul>';
      d.related_nodes.forEach(rn => { html += `<li>${escHtml(rn.title)}</li>`; });
      html += '</ul></div>';
    }

    return html;
  }

  // ── Utilities ──────────────────────────────────────────────────────────────
  function hexTint(hex, ratio) {
    const clean = hex.replace('#', '');
    if (clean.length !== 6) return '#f9f9f9';
    const r = parseInt(clean.slice(0, 2), 16);
    const g = parseInt(clean.slice(2, 4), 16);
    const b = parseInt(clean.slice(4, 6), 16);
    return `rgb(${Math.round(r * ratio + 255 * (1 - ratio))},${Math.round(g * ratio + 255 * (1 - ratio))},${Math.round(b * ratio + 255 * (1 - ratio))})`;
  }

  function escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;');
  }
})(Drupal, drupalSettings, once);
