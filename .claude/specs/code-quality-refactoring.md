# Code Quality & Refactoring Recommendations
**Ultimate Hall WordPress Theme**
**Analysis Date:** December 9, 2025
**Theme Version:** 1.0

## Table of Contents
- [Critical Issues](#critical-issues)
- [High Priority](#high-priority)
- [Medium Priority](#medium-priority)
- [Low Priority](#low-priority)
- [Quick Wins](#quick-wins)
- [Summary](#summary)

---

## Critical Issues

### CRIT-001: Duplicate SCSS Profile Header Files
**Severity:** Critical
**Effort:** Low
**Impact:** High

**Problem:**
Two files contain 100% identical code (48 lines each):
- `scss/templates/single-member/_profile-header.scss`
- `scss/templates/special-merit/_profile-header.scss`

**Impact:**
Any style change requires updating both files, leading to maintenance burden and risk of inconsistency.

**Recommendation:**
Create a shared partial SCSS file or mixin for profile headers.

**Implementation:**
1. Create `scss/templates/shared/_profile-header.scss` with the common code
2. Import in both `single-member.scss` and `special-merit.scss`
3. Delete duplicate code from both original files

**Files to Modify:**
- Create: `scss/templates/shared/_profile-header.scss`
- Modify: `scss/templates/single-member.scss`
- Modify: `scss/templates/special-merit.scss`
- Delete content from: `scss/templates/single-member/_profile-header.scss` and `scss/templates/special-merit/_profile-header.scss`

---

### CRIT-002: Function Name Collisions in posts_where Filters
**Severity:** Critical
**Effort:** Low
**Impact:** High

**Problem:**
Three template files define the same function name `my_posts_where()`, causing conflicts:
- `templates/single-team/members.php:5-10`
- `templates/single-team/national-team-members.php:5-10`
- `templates/single-tournaments/members.php:5-16`

**Impact:**
Function naming collision leads to unpredictable behavior when multiple templates load.

**Recommendation:**
Use unique filter names or create a reusable helper function with anonymous functions.

**Implementation Option 1 (Quick Fix):**
```php
// In templates/single-team/members.php
add_filter('posts_where', function($where) {
    $where = str_replace("meta_key = 'playing_career_$", "meta_key LIKE 'playing_career_%", $where);
    return $where;
});
```

**Implementation Option 2 (Better):**
Create a helper function in `functions.php`:
```php
function bearsmith_modify_meta_query($prefix) {
    return function($where) use ($prefix) {
        $where = str_replace("meta_key = '{$prefix}_$", "meta_key LIKE '{$prefix}_%", $where);
        return $where;
    };
}

// Usage in templates:
add_filter('posts_where', bearsmith_modify_meta_query('playing_career'));
```

**Files to Modify:**
- `templates/single-team/members.php`
- `templates/single-team/national-team-members.php`
- `templates/single-tournaments/members.php`
- `functions.php` (if using Option 2)

---

### CRIT-003: Duplicate SCSS Mixin Definition
**Severity:** Critical
**Effort:** Trivial
**Impact:** Low (no current errors but indicates copy-paste mistake)

**Problem:**
`scss/mixins/_media-queries.scss` defines `desktop-large` mixin twice:
- Lines 33-37
- Lines 39-43 (duplicate)

**Impact:**
Second definition overwrites first. Not causing errors but indicates sloppy copy-paste.

**Recommendation:**
Remove the duplicate definition.

**Files to Modify:**
- `scss/mixins/_media-queries.scss` (remove lines 39-43)

---

## High Priority

### HIGH-001: Repeated WP_Query Pattern for Members by Class
**Severity:** High
**Effort:** Medium
**Impact:** High

**Problem:**
Same query logic duplicated in 4+ files with minor variations:
- `single-year.php:4-17`
- `templates/archive-member/classes.php:20-33`
- `templates/home/latest-class.php:22-34`
- `blocks/hall-class/hall-class.php:39-52`

**Current Pattern:**
```php
$args = array(
    'post_type' => 'member',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => 'meta_class',
            'compare' => '=',
            'value' => $class_ID,
        ),
    )
);
$query = new WP_Query($args);
```

**Recommendation:**
Create a helper function in `functions.php`.

**Implementation:**
```php
/**
 * Get members by class/year
 *
 * @param int $class_id The class/year post ID
 * @param array $args Additional WP_Query arguments to merge
 * @return WP_Query
 */
function bearsmith_get_members_by_class($class_id, $args = array()) {
    $default_args = array(
        'post_type' => 'member',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'meta_class',
                'compare' => '=',
                'value' => $class_id,
            ),
        )
    );

    $merged_args = array_merge($default_args, $args);
    return new WP_Query($merged_args);
}

// Usage:
$query = bearsmith_get_members_by_class($class_ID);
```

**Files to Modify:**
- Create function in: `functions.php` or `functions/query-helpers.php`
- Replace query in: `single-year.php`
- Replace query in: `templates/archive-member/classes.php`
- Replace query in: `templates/home/latest-class.php`
- Replace query in: `blocks/hall-class/hall-class.php`

---

### HIGH-002: Hardcoded Inaugural Year "2004"
**Severity:** High
**Effort:** Medium
**Impact:** Medium

**Problem:**
Business logic checking for year "2004" is hardcoded in 5 locations:
- `single-year.php:29`
- `templates/archive-member/classes.php:10-14`
- `templates/single-member/profile-header.php:11`
- `templates/special-merit/profile-header.php:9`
- `templates/special-merit/introduction.php`

**Current Pattern:**
```php
if($year == '2004'): ?>
    Inaugural Class of <?php echo $year; ?>
<?php else: ?>
    Class of <?php echo $year; ?>
<?php endif; ?>
```

**Recommendation:**
Create a constant or ACF option for inaugural year.

**Implementation Option 1 (Constant):**
```php
// In functions.php
define('BEARSMITH_INAUGURAL_YEAR', '2004');

// Usage:
if($year == BEARSMITH_INAUGURAL_YEAR):
```

**Implementation Option 2 (Helper Function):**
```php
// In functions.php
function bearsmith_get_class_label($year) {
    $inaugural_year = '2004'; // Could also be ACF option
    return ($year == $inaugural_year) ? "Inaugural Class of {$year}" : "Class of {$year}";
}

// Usage:
echo bearsmith_get_class_label($year);
```

**Files to Modify:**
- `functions.php` (add constant or helper)
- `single-year.php`
- `templates/archive-member/classes.php`
- `templates/single-member/profile-header.php`
- `templates/special-merit/profile-header.php`
- `templates/special-merit/introduction.php`

---

### HIGH-003: O(n³) Performance Issue in Teammates Calculation
**Severity:** High
**Effort:** High
**Impact:** High (performance)

**Problem:**
`templates/single-member/teammates.php:28-51` uses triple-nested loops:
- Loops through all tournaments (foreach)
  - Loops through ALL members in system (foreach)
    - Loops through that member's tournaments (foreach)

**Current Complexity:** O(n³)
With 1000 members and 20 tournaments each = 400,000+ iterations

**Current Code:**
```php
foreach($tournaments as $tournament) {
    foreach($members as $member) {
        $us_championships = get_field('us_championships', $member);
        if($us_championships) {
            foreach($us_championships as $us_championship) {
                if ($tournament['event'] == $us_championship['tournament']
                    && $tournament['team'] == $us_championship['team']) {
                    array_push($teammates, $member);
                }
            }
        }
    }
}
```

**Recommendation:**
Replace with optimized database query using meta_query.

**Implementation:**
```php
// Get unique tournament/team combinations for current member
$tournament_team_pairs = array();
foreach($tournaments as $tournament) {
    $tournament_team_pairs[] = array(
        'tournament' => $tournament['event'],
        'team' => $tournament['team']
    );
}

// Build complex meta query to find teammates
$meta_query = array('relation' => 'OR');
foreach($tournament_team_pairs as $pair) {
    $meta_query[] = array(
        'relation' => 'AND',
        array(
            'key' => 'us_championships_%_tournament',
            'value' => $pair['tournament'],
            'compare' => '='
        ),
        array(
            'key' => 'us_championships_%_team',
            'value' => $pair['team'],
            'compare' => '='
        )
    );
}

$teammates_query = new WP_Query(array(
    'post_type' => 'member',
    'posts_per_page' => -1,
    'post__not_in' => array(get_the_ID()), // Exclude current member
    'meta_query' => $meta_query
));

$teammates = $teammates_query->posts;
```

**Files to Modify:**
- `templates/single-member/teammates.php`

**Note:** This requires testing to ensure it works with ACF repeater field structure.

---

### HIGH-004: Repeated Page Header Template Pattern
**Severity:** High
**Effort:** Medium
**Impact:** Medium

**Problem:**
Nearly identical page header code appears in 7 locations:
- `templates/single-team/page-header.php`
- `templates/single-tournaments/page-header.php`
- `templates/single-events/page-header.php`
- `template-parts/global/page-header.php`
- Inline in: `single-year.php`
- Inline in: `archive-events.php`
- Inline in: `archive-member.php`

**Current Pattern:**
```php
<section class="page-header align-center grid">
    <h1><?php the_title(); ?></h1>
    <?php // Optional subtitle variations ?>
</section>
```

**Recommendation:**
Create single parameterized template part.

**Implementation:**
Create `template-parts/global/page-header-unified.php`:
```php
<?php
/**
 * Unified Page Header Template
 *
 * @param string $title - Main title (default: current post title)
 * @param string $subtitle - Optional subtitle
 * @param string $location - Optional location
 * @param string $alignment - center or left (default: center)
 * @param array $custom_classes - Additional CSS classes
 */

$title = $args['title'] ?? get_the_title();
$subtitle = $args['subtitle'] ?? '';
$location = $args['location'] ?? '';
$alignment = $args['alignment'] ?? 'center';
$custom_classes = $args['custom_classes'] ?? array();

$classes = array_merge(
    array('page-header', "align-{$alignment}", 'grid'),
    $custom_classes
);
?>

<section class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <h1><?php echo esc_html($title); ?></h1>

    <?php if($subtitle): ?>
        <h2><?php echo esc_html($subtitle); ?></h2>
    <?php endif; ?>

    <?php if($location): ?>
        <p class="location"><?php echo esc_html($location); ?></p>
    <?php endif; ?>
</section>
```

**Usage:**
```php
// Simple usage:
get_template_part('template-parts/global/page-header-unified');

// With subtitle:
get_template_part('template-parts/global/page-header-unified', null, array(
    'subtitle' => get_field('event_location')
));

// Custom:
get_template_part('template-parts/global/page-header-unified', null, array(
    'title' => 'Custom Title',
    'subtitle' => 'Subtitle text',
    'alignment' => 'left'
));
```

**Files to Modify:**
- Create: `template-parts/global/page-header-unified.php`
- Replace in all 7 locations listed above

---

## Medium Priority

### MED-001: Repeated Query Arguments Across Files
**Severity:** Medium
**Effort:** Low
**Impact:** Medium

**Problem:**
Same query arguments repeated across 13 files:
- `'posts_per_page' => -1` (13 occurrences)
- `'orderby' => 'title'` (11 occurrences)
- `'order' => 'ASC'` (11 occurrences)

**Recommendation:**
Create default args constant or helper function.

**Implementation:**
```php
// In functions.php
function bearsmith_default_query_args($post_type = 'post', $custom_args = array()) {
    $defaults = array(
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    );

    return array_merge($defaults, $custom_args);
}

// Usage:
$args = bearsmith_default_query_args('member', array(
    'meta_query' => array(...)
));
```

**Files Affected:**
13 files with WP_Query instances (can be identified with grep for `posts_per_page`)

---

### MED-002: SCSS Spacing Values Without Variables
**Severity:** Medium
**Effort:** Low
**Impact:** Medium

**Problem:**
Spacing patterns hardcoded throughout SCSS:
- `padding-bottom: 2rem` - 12 occurrences across 11 files
- `padding-bottom: 3rem` - 8 occurrences across 7 files
- `text-transform: uppercase` - 21 occurrences across 15 files
- `letter-spacing: 0.1em` - Multiple files

**Recommendation:**
Create SCSS spacing and typography variables.

**Implementation:**
In `scss/variables/_spacing.scss` (create if doesn't exist):
```scss
// Spacing Scale
$spacing-xs: 1rem;
$spacing-sm: 2rem;
$spacing-md: 3rem;
$spacing-lg: 4rem;
$spacing-xl: 6rem;

// Typography utilities
$text-uppercase: uppercase;
$letter-spacing-wide: 0.1em;
$letter-spacing-wider: 0.15em;
```

**Files to Modify:**
- Create: `scss/variables/_spacing.scss`
- Update imports in: `scss/style.scss`
- Replace hardcoded values in 15+ SCSS files

---

### MED-003: Repeated Section Header Style Patterns
**Severity:** Medium
**Effort:** Low
**Impact:** Low

**Problem:**
Similar section header patterns in `scss/typography/_headings.scss`:
- `.page-header` with `padding-bottom: 3rem`
- `.section-header` with varying padding

**Recommendation:**
Create SCSS mixin for section spacing.

**Implementation:**
```scss
@mixin section-spacing($size: 'medium') {
    @if $size == 'small' {
        padding-bottom: $spacing-sm;
    } @else if $size == 'medium' {
        padding-bottom: $spacing-md;
    } @else if $size == 'large' {
        padding-bottom: $spacing-lg;
    }
}

// Usage:
.page-header {
    @include section-spacing('medium');
}

.section-header {
    @include section-spacing('small');

    @include tablet {
        @include section-spacing('medium');
    }
}
```

**Files to Modify:**
- `scss/mixins/_utilities.scss` (create mixin)
- `scss/typography/_headings.scss`
- Other files using section headers

---

### MED-004: Gold Color Variable Inconsistency
**Severity:** Medium
**Effort:** Low
**Impact:** Low

**Problem:**
`scss/variables/_colors.scss` has both:
- Single `$gold` variable (line 7) - used 26 times
- Full gold scale `$gold-50` through `$gold-950` (lines 24-35) - barely used

**Recommendation:**
Either use the scale system or remove it. Don't maintain both.

**Implementation Option 1 (Keep simple):**
Remove the gold scale if not being used.

**Implementation Option 2 (Use scale):**
Define `$gold` as an alias:
```scss
// Gold scale
$gold-50: #fefce8;
$gold-100: #fef9c3;
// ... rest of scale
$gold-600: #ca8a04;  // Primary gold
$gold-950: #422006;

// Alias for backward compatibility
$gold: $gold-600;
```

**Files to Modify:**
- `scss/variables/_colors.scss`

---

### MED-005: Inconsistent Template Pattern (single-post.php)
**Severity:** Medium
**Effort:** Low
**Impact:** Low

**Problem:**
`single-post.php` contains 51 lines of inline logic, while all other `single-*.php` files delegate everything to template parts.

**Recommendation:**
Move `single-post.php` logic to template parts for consistency.

**Implementation:**
1. Create `templates/single-post/` directory
2. Break inline logic into template parts:
   - `templates/single-post/page-header.php`
   - `templates/single-post/content.php`
   - `templates/single-post/navigation.php`
3. Update `single-post.php` to use `get_template_part()` like others

**Files to Modify:**
- Create: `templates/single-post/` (directory and parts)
- Refactor: `single-post.php`

---

## Low Priority

### LOW-001: Dead Code - Unused Function
**Severity:** Low
**Effort:** Trivial
**Impact:** Minimal

**Problem:**
`functions/divisions.php` defines `bearsmith_global_vars()` (lines 13-25) but it's never called anywhere in the codebase.

**Recommendation:**
Remove the function or implement if needed.

**Files to Modify:**
- `functions/divisions.php`

---

### LOW-002: Commented Code in Templates
**Severity:** Low
**Effort:** Trivial
**Impact:** Minimal

**Problem:**
`single-tournaments.php:9` contains commented template part:
```php
<?php //get_template_part('templates/single-tournaments/years'); ?>
```

**Recommendation:**
Remove commented code or document why it's kept.

**Files to Modify:**
- `single-tournaments.php`

---

### LOW-003: JavaScript var Usage
**Severity:** Low
**Effort:** Trivial
**Impact:** Minimal

**Problem:**
`js/site.js` uses `var` instead of `const`/`let`.

**Recommendation:**
Update to modern ES6 syntax.

**Files to Modify:**
- `js/site.js`

---

### LOW-004: Hardcoded Slick Slider Settings
**Severity:** Low
**Effort:** Low
**Impact:** Minimal

**Problem:**
`js/site.js` has hardcoded slider settings:
```javascript
speed: 800,
autoplaySpeed: 5000
```

**Recommendation:**
Make configurable via data attributes or wp_localize_script.

**Files to Modify:**
- `js/site.js`
- Template files using `.member-gallery__slider`

---

## Quick Wins

### QUICK-001: Typo in Profile Header
**Severity:** Trivial
**Effort:** Trivial
**Impact:** Critical (user-facing)

**Problem:**
`templates/special-merit/profile-header.php:13` contains "hi" before title:
```php
<h1 class="name__title">hi<?php the_title(); ?></h1>
```

**Fix:**
```php
<h1 class="name__title"><?php the_title(); ?></h1>
```

**Files to Modify:**
- `templates/special-merit/profile-header.php`

---

## Summary

### By Severity
- **Critical:** 3 issues
- **High Priority:** 4 issues
- **Medium Priority:** 5 issues
- **Low Priority:** 4 issues
- **Quick Wins:** 1 issue

### Estimated Impact
- **15-20% of template/query code** can be consolidated
- **Performance improvement:** Significant (teammates query optimization)
- **Maintenance reduction:** High (eliminate duplicate files and patterns)
- **Code consistency:** High (standardized helpers and patterns)

### Recommended Implementation Order
1. **QUICK-001** - Fix "hi" typo (30 seconds)
2. **CRIT-002** - Fix function name collisions (15 minutes)
3. **CRIT-003** - Remove duplicate SCSS mixin (1 minute)
4. **CRIT-001** - Consolidate SCSS profile headers (30 minutes)
5. **HIGH-001** - Create member query helper (1 hour)
6. **HIGH-002** - Abstract inaugural year logic (30 minutes)
7. **HIGH-004** - Create unified page header template (1 hour)
8. **HIGH-003** - Optimize teammates query (2-3 hours, requires testing)
9. **MED-001 through MED-005** - Cleanup and consistency (3-4 hours total)
10. **LOW-001 through LOW-004** - Nice-to-haves (1-2 hours total)

### Total Estimated Effort
- **Critical + Quick Wins:** 1 hour
- **High Priority:** 4-5 hours
- **Medium Priority:** 3-4 hours
- **Low Priority:** 1-2 hours
- **Grand Total:** 9-12 hours for complete refactoring

---

**Document Version:** 1.0
**Last Updated:** December 9, 2025
**Next Review:** After completing critical and high-priority items
