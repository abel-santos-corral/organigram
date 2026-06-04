# Organigram

A Drupal 11 module that provides a fully configurable organizational chart system built on a custom **Organigram node** content type and a **Organigram Node Type** config entity, rendered in the browser using [D3.js v7](https://d3js.org/).

---

## Table of Contents

1. [Functional Overview](#1-functional-overview)
2. [Benefits](#2-benefits)
3. [Scope](#3-scope)
4. [Out of Scope](#4-out-of-scope)
5. [Architecture Summary](#5-architecture-summary)
6. [Installation](#6-installation)
7. [The Organigram Node Type Config Entity](#7-the-organigram-node-type-config-entity)
   - [What it is](#what-it-is)
   - [Default presets](#default-presets)
   - [Managing Organigram Node Types](#managing-organigram-node-types)
   - [How it drives the renderer](#how-it-drives-the-renderer)
8. [The Organigram node Content Type](#8-the-organigram-node-content-type)
9. [Building an Organigram](#9-building-an-organigram)
10. [The JSON Data Endpoint](#10-the-json-data-endpoint)
11. [Permissions](#11-permissions)
12. [Updating from v1](#12-updating-from-v1)
13. [Running Tests](#13-running-tests)

---

## 1. Functional Overview

The Organigram module lets a site team build and maintain one or more hierarchical organisational charts entirely through the Drupal admin interface — no code changes required after installation.

Each chart is a tree of **Organigram node** content items. Nodes represent any organisational unit: a department, a team, a position, a working group. Each node can carry rich editorial content that appears in a slide-in detail panel when a visitor clicks it:

- **Position title** and the **name of the responsible person** (or a *Vacant* label if the position is unfilled)
- **CV** — an optional PDF document link that opens in a new tab
- **Declaration of Interest** — an optional PDF document link that opens in a new tab
- **Description & Scope of Work** — one or more titled bullet-point lists, authored through CKEditor

The visual appearance of every node — font size, font colour, background colour, connector line weight, colour, and dash pattern — is controlled by a **Organigram Node Type** config entity. Webmasters define as many types as the organisation needs and assign one to each node. The D3 renderer reads these values directly from the JSON data endpoint, so visual changes in the admin UI propagate to all organigrams immediately on next page load with no cache rebuild.

---

## 2. Benefits

**Editorial independence.** Content editors create and update organisational data through standard Drupal node forms. No developer involvement is needed to add a node, fill a vacancy, upload a CV, or restructure the hierarchy.

**Webmaster control over visual style.** Organigram Node Types are a Drupal config entity managed at *Administration → Structure → Organigram Node Types*. A webmaster can change the colour scheme of an entire category of nodes (e.g. make all departments purple instead of blue) and the change takes effect everywhere those nodes appear.

**Deployment-safe configuration.** Because Organigram Node Types are config entities they travel through the normal Drupal configuration management workflow: `drush config-export` / `drush config-import`. Visual styles can be developed locally, reviewed in staging, and deployed to production without database imports or manual UI steps.

**Hierarchy without limits.** The tree is built from `field_parent_node` entity references, not taxonomy. There is no depth limit and no restriction on how many children a node may have. The D3 renderer collapses subtrees on double-click so large charts remain navigable.

**Matrix-ready.** Beyond the primary hierarchy, nodes support `field_related_nodes` for dotted-line, chapter, or advisory relationships. These are exposed in the JSON endpoint and available for Phase 2 graph rendering.

**Accessibility.** The rendered SVG carries `role="button"`, `tabindex`, `aria-label`, and keyboard handlers (Enter/Space to open the detail panel, Escape to close it).

---

## 3. Scope

The following is within scope of this module:

- Definition of the `organigram_node` content type with all fields described in this document
- Definition of the `scope_work_section` Paragraph type for structured scope content
- Definition of the `organigram_node_type` config entity and its CRUD admin interface
- Optional starter Organigram Node Type presets shipped by the Organigram node types kickstarter submodule (`department`, `direction`, `role`, `task_force`, `team`, `unit`)
- A D3.js tree renderer attached to any `organigram_node` via `/organigram/{nid}`
- A JSON data endpoint at `/organigram/{nid}/data` that serialises the full subtree rooted at any node, including resolved Organigram Node Type visual settings
- Department cluster backgrounds: nodes sharing a Organigram Node Type colour are grouped visually in the SVG
- A slide-in detail panel showing position title, person name, CV link, Declaration of Interest link, and Scope of Work sections
- Vacant position handling: when `field_is_vacant` is true, the CV, Declaration of Interest, and responsible person fields are hidden in both the renderer and the JSON output
- Collapsible subtrees (double-click to collapse/expand)
- Display order via `field_display_weight`
- An update hook (`organigram_update_10001`) for migrating existing sites from the previous `list_string` field type to the `entity_reference` → `organigram_node_type` model

---

## 4. Out of Scope

The following is explicitly **not** covered by this module in its current version:

- **Multi-root / forest charts.** Each organigram has a single root node. Multiple disconnected trees are separate organigrams.
- **Graph mode / matrix rendering.** `field_related_nodes` is stored and exposed in the JSON but the D3 renderer does not yet draw dotted-line or lateral connections. This is planned for Phase 2.
- **Search or filter within the chart.** Nodes cannot be searched or highlighted by name from the frontend.
- **Print / export to PDF or image.** The SVG can be printed via the browser but no dedicated export feature is provided.
- **Drag-and-drop reordering in the frontend.** Hierarchy is managed in the node edit form.
- **Translations / multilingual content.** All fields are defined as non-translatable. Multilingual support requires additional field configuration.
- **Access control per node.** Visibility follows Drupal core node access. There is no per-node or per-subtree visibility restriction within the module.
- **Audit log / revision history.** Nodes use Drupal's standard node revision system but the module does not add organigram-specific audit features.
- **REST or JSON:API write operations.** The data endpoint is read-only.

---

## 5. Architecture Summary

```
organigram/
├── src/
│   ├── Entity/
│   │   ├── OrganigramNodeType.php           # Config entity (box + line visual settings)
│   │   └── OrganigramNodeTypeInterface.php  # Typed interface for OrganigramNodeType
│   ├── Form/
│   │   └── OrganigramNodeTypeForm.php       # Add / Edit form with live preview
│   ├── OrganigramNodeTypeListBuilder.php    # Admin listing with inline SVG preview
│   └── Controller/
│       └── OrganigramController.php # Display page + JSON data endpoint
├── config/
│   ├── install/                    # Installs on drush en organigram
│   │   ├── node.type.organigram_node.yml
│   │   ├── field.storage.node.*    # All field storage definitions
│   │   ├── field.field.node.*      # All field instance definitions
│   │   ├── field.storage.paragraph.*
│   │   ├── field.field.paragraph.*
│   │   ├── core.entity_form_display.*
│   │   └── core.entity_view_display.*
│   └── schema/
│       └── organigram.schema.yml   # Config schema for OrganigramNodeType entity
├── modules/
│   └── organigram_node_types_kickstarter/
│       └── config/optional/
│           ├── organigram.organigram_node_type.department.yml
│           ├── organigram.organigram_node_type.direction.yml
│           ├── organigram.organigram_node_type.role.yml
│           ├── organigram.organigram_node_type.task_force.yml
│           ├── organigram.organigram_node_type.team.yml
│           └── organigram.organigram_node_type.unit.yml
├── js/organigram.js                # D3 v7 renderer (Drupal.behaviors)
├── css/organigram.css              # Layout, modal, node styles
└── templates/
    └── organigram-display.html.twig
```

**Data flow:**

```
Drupal admin  →  organigram_node nodes + organigram_node_type config
                        ↓
          OrganigramController::data()
          Traverses field_parent_node tree,
          resolves OrganigramNodeType entity per node,
          serialises to JSON
                        ↓
          /organigram/{nid}/data  (JSON)
                        ↓
          organigram.js  (D3 v7)
          Reads organigram_node_type_settings per node,
          renders SVG tree with styled boxes and lines,
          click → slide-in detail panel
```

---

## 6. Installation

### Requirements

| Requirement | Version                              |
|---|--------------------------------------|
| Drupal | ^11 \|\| ^12                         |
| PHP | ^8.2                                 |
| Node.js / npm | Not required — D3 is loaded from CDN |

### Steps

**1. Place the module**

```bash
# Option A — copy manually
cp -r organigram web/modules/custom/organigram

# Option B — if distributed as a Composer package
composer require drupal/organigram
```

**2. Enable the module**

```bash
drush en organigram -y
drush cr
```

This single command installs all of the following in the correct dependency order:

- The `organigram_node` content type and all 22 fields
- The `scope_work_section` Paragraph type and its fields
- The `organigram_node_type` config entity definition
- Starter Organigram Node Type presets are provided by the optional Organigram node types kickstarter submodule.
- All form and view display configurations

**3. Verify**

After enabling, confirm the following exist:

| What | Where |
|---|---|
| Organigram Node Types admin | `/admin/structure/organigram-node-type` |
| Create a Organigram node | `/node/add/organigram-node` |
| Content list | `/admin/content` (filter by Organigram node) |

**4. (Optional) Configure file system for private files**

CV and Declaration of Interest documents are stored in the `private` file scheme. If your Drupal installation does not already have a private files directory configured, add this to `settings.php`:

```php
$settings['file_private_path'] = '/path/outside/webroot/private';
```

Then run:

```bash
drush cr
```

---

## 5. The Organigram Node Type Config Entity

### What it is

A **Organigram Node Type** is a named visual style definition. It tells the organigram renderer exactly how to draw nodes of that category and the connector lines leading to them.

Each Organigram Node Type stores:

| Property | Field | Example |
|---|---|---|
| Machine name | `id` | `department` |
| Human label | `label` | `Department` |
| **Box — font size** | `box_font_size` | `11` (px) |
| **Box — font colour** | `box_font_color` | `#ffffff` |
| **Box — background** | `box_background` | `#0055AA` |
| **Line — width** | `line_size` | `0.5` / `1` / `2` (px) |
| **Line — colour** | `line_color` | `#0055AA` |
| **Line — type** | `line_type` | `solid` / `dashed` / `dotted` / `dashdot` |

Organigram Node Types are **configuration**, not content. This has two important consequences:

1. They are managed with `drush config-export` / `drush config-import` and travel through your deployment pipeline like any other Drupal configuration.
2. They do not have revisions, translations, or workflow states — they are global settings.

### Default presets

Starter Organigram Node Types are shipped with the Organigram node types kickstarter submodule as optional configuration. They are created automatically on `drush en organigram_node_types_kickstarter` and are designed to cover the most common organigram structure immediately:

#### `department`
```yaml
label: Department
box_font_size: 11
box_font_color: '#3A2800'
box_background: '#F5D04A'
line_size: '1.5'
line_color: '#C9A800'
line_type: solid
```
Used for top-level grouping nodes. The D3 renderer detects nodes that share a Organigram Node Type background colour and draws a shaded cluster rectangle behind them, labelled with the top node's title. This makes departments visually distinct sections in the chart.

#### `direction`
```yaml
label: Direction
box_font_size: 11
box_font_color: '#FFFFFF'
box_background: '#004494'
line_size: '2'
line_color: '#002F6C'
line_type: solid
```
Used for top-level direction nodes.

#### `role`
```yaml
label: Role
box_font_size: 11
box_font_color: '#5A2D00'
box_background: '#FDF6E3'
line_size: '1.5'
line_color: '#C9A800'
line_type: solid
```
A functional role as distinct from a formal position.

### Managing Organigram Node Types

Navigate to **Administration → Structure → Organigram Node Types** (`/admin/structure/organigram-node-type`).

The listing page shows each type with a live SVG line preview and a styled "Aa" sample box so you can see the result without opening the edit form.

To **add a type** — for example, a `squad` for an agile organisation — click **Add Organigram Node Type**, give it a machine name and label, pick your colours with the native colour pickers, choose a line weight and style, and save. The new type is immediately available in the **Organigram node Type** field on all `organigram_node` edit forms.

To **remove a type** that is in use, Drupal will warn you that nodes referencing it will lose their type assignment. Reassign nodes first.

### How it drives the renderer

When the D3 renderer fetches `/organigram/{nid}/data`, every node in the JSON includes a `organigram_node_type_settings` object:

```json
{
  "id": 42,
  "title": "Head of Engineering",
  "organigram_node_type": "direction",
  "organigram_node_type_settings": {
    "id": "direction",
    "label": "Direction",
    "box_font_size": 11,
    "box_font_color": "#FFFFFF",
    "box_background": "#004494",
    "line_size": "2",
    "line_color": "#002F6C",
    "line_type": "solid",
    "line_dash_array": "none"
  }
}
```

The renderer applies these values directly to each SVG `<rect>`, `<text>`, and `<line>` element. There are no colour or size values hardcoded in `organigram.js`. Changing a Organigram Node Type in the admin UI changes the rendering for every node of that type on every organigram on the site, on the next page load.

---

## 8. The Organigram node Content Type

Machine name: `organigram_node`

Each node is one box in the organigram. A complete field reference:

### A. Core identity

| Label           | Machine name            | Type | Required |
|-----------------|-------------------------|---|---|
| Title           | `title`                 | Core | ✅ |
| Organigram node Type | `field_organigram_node_type` | Entity reference → `organigram_node_type` | ✅ |
| Hidden          | `field_is_hidden` | Boolean | |

### B. Hierarchy

| Label | Machine name | Type | Notes |
|---|---|---|---|
| Parent Node | `field_parent_node` | Entity reference → `organigram_node` | Leave empty for the root |
| Related Nodes | `field_related_nodes` | Entity reference → `organigram_node` (multiple) | Dotted lines, matrix, chapters |
| Relation Type | `field_relation_type` | List (text) | `hierarchical`, `functional`, `project`, `chapter`, `advisory`… |

### C. Visual

| Label | Machine name | Type | Notes |
|---|---|---|---|
| Display Order | `field_display_weight` | Integer | Siblings sorted ascending |
| Collapsed by Default | `field_collapsed_default` | Boolean | Subtree starts collapsed |

### D. Modal — position information

| Label | Machine name | Type | Notes |
|---|---|---|---|
| Position Title | `field_position_title` | String | Falls back to node title |
| Is Vacant | `field_is_vacant` | Boolean | Hides person, CV, DoI when true |
| Responsible Person Name | `field_responsible_name` | String | |
| Responsible Person Photo | `field_responsible_photo` | Image | |

### E. Documents

| Label | Machine name | Type | Notes |
|---|---|---|---|
| CV Document | `field_cv_document` | File (PDF, private) | Opens in new tab |
| Declaration of Interest | `field_declaration_interest` | File (PDF, private) | Opens in new tab |

### G. Metadata

| Label | Machine name | Type |
|---|---|---|
| Start Date | `field_start_date` | Date |
| End Date | `field_end_date` | Date |
| Internal Notes | `field_scope_of_work` | Long text |

---

## 9. Building an Organigram

1. **Create the root node.** Go to `/node/add/organigram-node`. Leave *Parent Node* empty. Assign a Organigram Node Type (e.g. `department`). This is the top of your chart.

2. **Create child nodes.** For each department head, team, or position, create a new Organigram node and set its *Parent Node* to the node above it in the hierarchy.

3. **Use Display Order** (`field_display_weight`) to control the left-to-right order of siblings. Nodes with lower numbers appear first.

4. **Fill positions.** Set *Is Vacant* to unchecked, enter the responsible person's name, upload a CV and Declaration of Interest PDF if available, and add Scope of Work.

5. **Mark vacancies.** For unfilled positions, check *Is Vacant*. The node renders with a dashed border, shows "Vacant" in the person slot, and hides the CV and DoI links.

6. **View the chart.** Navigate to `/organigram/{nid}` where `{nid}` is the NID of your root node.

---

## 10. The JSON Data Endpoint

```
GET /organigram/{nid}/data
```

Returns the complete subtree rooted at `{nid}` as JSON. The structure mirrors the `field_parent_node` hierarchy recursively (maximum depth 15). Each node object includes all fields, the resolved `organigram_node_type_settings` object, and a `children` array.

Requires `access content` permission. Respects Drupal node access controls — unpublished nodes are excluded.

Useful for debugging, for building alternative frontends, or for integrating with other tools.

---

## 11. Permissions

| Permission | Role | Description |
|---|---|---|
| `access content` | Authenticated / Anonymous | View organigrams and the JSON endpoint |
| `create organigram_node content` | Editor | Create new nodes |
| `edit any organigram_node content` | Editor | Edit existing nodes |
| `administer organigram` | Webmaster | Manage Organigram Node Types at `/admin/structure/organigram-node-type` |

---

## 12. Updating from v1

If you installed the module before the `organigram_node_type` config entity was introduced (i.e. when `field_organigram_node_type` was a `list_string` field), run the provided update hook:

```bash
drush updb -y
drush cr
```

The update hook `organigram_update_10001()` performs the following steps automatically:

1. Reads all existing `field_organigram_node_type` values from the database before touching anything.
2. Deletes the old `list_string` field storage and instance configuration.
3. Drops the old database column.
4. Re-installs the new `entity_reference` field storage and instance from `config/install`.
5. Re-inserts the saved values — the old machine-name values (`department`, `position`, etc.) are identical to the new Organigram Node Type IDs, so no data transformation is needed.

> **Note:** The update hook also triggers `config.installer` to install the default Organigram Node Type presets if they do not already exist. Existing values not matching a preset ID (e.g. `squad`, `tribe`) will result in nodes with a null Organigram Node Type. These nodes must be reassigned manually after the update.

---

## 13. Running Tests

The Organigram project includes Unit, Kernel, Functional and Functional Javascript tests across the main module and its renderer/block submodules.

### Run all Organigram tests

Execute all tests belonging to the Organigram project, including submodules such as `organigram_d3` and `organigram_block`:

```bash
docker compose exec web ./vendor/bin/phpunit \
lib/modules/organigram
```

### Run only tests from the main Organigram module

```bash
docker compose exec web ./vendor/bin/phpunit \
lib/modules/organigram/tests
```

### Run tests by test suite

**Unit tests**

```bash
docker compose exec web ./vendor/bin/phpunit \
--testsuite unit \
lib/modules/organigram
```

**Kernel tests**

```bash
docker compose exec web ./vendor/bin/phpunit \
--testsuite kernel \
lib/modules/organigram
```

**Functional tests**

```bash
docker compose exec web ./vendor/bin/phpunit \
--testsuite functional \
lib/modules/organigram
```

**Functional Javascript tests**

```bash
docker compose exec web ./vendor/bin/phpunit \
--testsuite functional-javascript \
lib/modules/organigram
```

### Run a single test class

Example:

```bash
docker compose exec web ./vendor/bin/phpunit \
lib/modules/organigram/tests/src/Functional/OrganigramPageCacheTest.php
```

### Run a single test method

Example:

```bash
docker compose exec web ./vendor/bin/phpunit \
--filter testPageCacheHeadersPresent \
lib/modules/organigram/tests/src/Functional/OrganigramPageCacheTest.php
```
