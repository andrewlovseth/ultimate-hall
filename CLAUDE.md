# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Theme Overview

WordPress theme for the Ultimate Hall of Fame website. This is the theme repository, separate from the main DDEV project at `wp/wp-content/themes/ultimate-hall` in the parent repo.

## Commands

```bash
# Dev server with live reload (watches SCSS and PHP)
bun run dev

# Compile CSS only
bun run build
```

The dev server proxies `https://ultimate-hall.ddev.site` at `http://localhost:3030`. Ensure DDEV is running in the parent project.

## Architecture

### Custom Post Types (via ACF)
- **member** - Hall of Fame inductees (the core content type)
- **team** - Teams that members played for
- **tournaments** - Championship tournaments
- **events** - Hall of Fame events
- **division** - Playing divisions (Men's, Women's, Mixed)
- **year** - Induction classes (registered in PHP, not ACF; URL slug: `/class/`)

### Function Organization
Functions are split into focused files in `/functions/`:
- `acf.php` - ACF options pages, relationship field sorting, `year` CPT registration
- `query-helpers.php` - `bearsmith_default_query_args()`
- `member-helpers.php` - `bearsmith_get_members_by_class()` for querying inductees
- `inaugural-helpers.php` - Inaugural class-specific logic
- `register-blocks.php` - ACF block registration
- `model.php` - **the data layer**: member career/results/service readers, year parsing and formatting, team groups, the derived member index (`_uh_team`, `_uh_tournament`) and `uh_sync_member()`
- `cli.php` - `wp uh migrate|reindex|merge|cleanup-legacy`
- `rest.php` - hides private member fields from logged-out REST readers
- `abilities.php` - 15 WordPress Abilities (`uhof/*`) for agent editing, with a per-member change log and undo
- `mcp.php` - serves those abilities as MCP tools at `/wp-json/uhof/mcp` (needs the MCP Adapter plugin)
- `admin-columns.php` - wp-admin list columns, sorts, filters and remembered sorts for members, teams, tournaments and media; replaces Admin Columns Pro

### Template Structure
**Single templates** load sections from `/templates/{post-type}/`:
```php
// single-member.php loads:
get_template_part('templates/single-member/profile-header');
get_template_part('templates/single-member/vitals');
// etc.
```

**Reusable partials** live in `/template-parts/`:
- `template-parts/global/member.php` - Member card component
- `template-parts/global/page-header.php` - Standard page header
- `template-parts/header/`, `template-parts/footer/` - Site chrome

### ACF Blocks
Located in `/blocks/{block-name}/`:
- `hall-class` - Displays a grid of members filtered by induction year

### SCSS Organization
```
scss/
├── style.scss          # Main entry, imports all partials
├── _normalize.scss
├── variables/          # Colors, fonts
├── mixins/
├── layout/            # Grid, containers
├── elements/          # Buttons, forms
├── blocks/            # ACF block styles
├── header/, footer/
└── templates/         # Page-specific styles
```

## Key Patterns

### Querying Members by Class
```php
$query = bearsmith_get_members_by_class($year_ID);
// Returns WP_Query, ordered alphabetically by default
```

### Default Query Args
```php
$args = bearsmith_default_query_args('member', ['posts_per_page' => 10]);
```

### Member data model
A member has three repeaters, all edited in wp-admin or through the abilities:
- `career`: team (post object), start_year, end_year, to_present, approximate, note. One row per continuous stint.
- `results`: tournament, team, placement (select from `uh_placements()`). The tournament's `details_series` (us, world, pro) decides which table it shows in.
- `service`: start_year, end_year, to_present, approximate, description.

Never query repeater rows with LIKE meta queries. Use `uh_members_for_team()` / `uh_members_for_tournament()`, which read the index that `uh_sync_member()` rebuilds on admin saves, REST saves and `wp uh reindex`. If you write meta directly, run `wp uh reindex` afterwards.

### Deploys
Production theme is deployed by WP Pusher from `master` (push-to-deploy off: click Update in WP Pusher), or by rsyncing a `git archive` of master over SSH (`ssh uhof`, site root `~/files`). Back up first: `wp db export` into `/sites/ultimatehall.org/`.

## ACF Field Groups
JSON definitions in `/acf-json/`. Field groups sync automatically when ACF Pro is active.

Local JSON overrides the field groups stored in the database. Key field prefixes on member posts:
- `meta_*` - Classification fields (class, division, induction type)
- `photos_*` - Image fields
- `introduction_*` - Bio/intro content
