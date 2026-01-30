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

The dev server proxies `https://ultimatehall.dev` at `http://localhost:3030`. Ensure DDEV is running in the parent project.

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
- `query-helpers.php` - `bearsmith_default_query_args()`, repeater meta query helper
- `member-helpers.php` - `bearsmith_get_members_by_class()` for querying inductees
- `inaugural-helpers.php` - Inaugural class-specific logic
- `register-blocks.php` - ACF block registration

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

### ACF Repeater Meta Queries
For querying across repeater field rows:
```php
add_filter('posts_where', bearsmith_modify_repeater_meta_query('playing_career'));
$query = new WP_Query($args);
remove_filter('posts_where', bearsmith_modify_repeater_meta_query('playing_career'));
```

## ACF Field Groups
JSON definitions in `/acf-json/`. Field groups sync automatically when ACF Pro is active.

Key field prefixes on member posts:
- `meta_*` - Classification fields (class, division, induction type)
- `photos_*` - Image fields
- `introduction_*` - Bio/intro content
