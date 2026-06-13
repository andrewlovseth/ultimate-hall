<?php
/**
 * Search results template (/?s=term)
 *
 * Server-rendered counterpart to the omnibox dropdown. Calls the same
 * bearsmith_grouped_search() backend as the REST endpoint, so both surfaces
 * always return identical groups, sub-lines, and member photos.
 *
 * Layout is the approved "sidebar rail" design (specimens/search-results-
 * refine.html, Option B): type filters with live counts in a left rail,
 * grouped results in the main column. Member rows lead with headshot
 * thumbnails (initials fallback), teams with initials, classes and pages
 * with archival Lucide icons via bearsmith_search_icon().
 *
 * Optional ?type=members|teams|classes|pages filters the page to one group;
 * invalid values fall back to all groups. Filtered views go deeper per
 * group (50 vs 20) since they only render one section.
 */

// Raw (unescaped) query so WP_Query sees the real term; escape at output.
$search_query = trim( sanitize_text_field( get_search_query( false ) ) );

// Whitelist-validate the optional type filter. Anything else → null → all groups.
$valid_types = array( 'members', 'teams', 'classes', 'pages' );
$type        = null;
if ( isset( $_GET['type'] ) ) {
    $requested_type = sanitize_key( wp_unslash( $_GET['type'] ) );
    if ( in_array( $requested_type, $valid_types, true ) ) {
        $type = $requested_type;
    }
}

$has_query = mb_strlen( $search_query ) >= 2;

// All view caps each group at 20; a filtered view shows one group, so go to 50.
$per_group      = $type ? 50 : 20;
$display_groups = $has_query ? bearsmith_grouped_search( $search_query, $per_group, $type ) : array();

/*
 * The rail always shows counts for all four groups. When a type filter is
 * active, the display call above only covers one group, so run a second
 * cheap pass (1 item per group — found_posts still reports full totals).
 */
if ( $has_query && $type ) {
    $count_groups = bearsmith_grouped_search( $search_query, 1, null );
} else {
    $count_groups = $display_groups;
}

$total        = 0;
$group_counts = array();
foreach ( $count_groups as $count_group ) {
    $group_counts[ $count_group['key'] ] = $count_group['count'];
    $total                              += $count_group['count'];
}

// Count of results actually visible on this page (respects the type filter).
$shown_total = 0;
foreach ( $display_groups as $display_group ) {
    $shown_total += $display_group['count'];
}

$group_labels = array(
    'members' => 'Members',
    'teams'   => 'Teams',
    'classes' => 'Classes',
    'pages'   => 'Pages',
);

// Archival icon per group — shared between the rail filters and group heads.
$group_icons = array(
    'members' => 'user-round',
    'teams'   => 'flag',
    'classes' => 'medal',
    'pages'   => 'scroll-text',
);

// Filter/more-link URL builder. urlencode() because add_query_arg() expects encoded values.
$base_url = home_url( '/' );
$all_url  = add_query_arg( 's', urlencode( $search_query ), $base_url );

get_header(); ?>


<section class="search-results">

    <?php
        // Theme-convention page header (outputs its own .page-header.grid section).
        // The unified part echoes title raw, so the query is escaped here.
        if ( $has_query ) {
            $header_args = array(
                'title'    => sprintf( 'Search results for &ldquo;%s&rdquo;', esc_html( $search_query ) ),
                'subtitle' => sprintf( '%s %s', number_format_i18n( $total ), 1 === $total ? 'result' : 'results' ),
            );
        } else {
            $header_args = array( 'title' => 'Search' );
        }
        get_template_part( 'template-parts/global/page-header-unified', null, $header_args );
    ?>

    <?php if ( $has_query && $total > 0 ) : ?>

        <div class="search-results__layout">

            <aside class="search-results__rail">
                <h2 class="search-results__rail-title">Filter results</h2>

                <nav class="search-results__filters">
                    <a class="search-results__filter<?php echo null === $type ? ' is-active' : ''; ?>" href="<?php echo esc_url( $all_url ); ?>">
                        <span class="search-results__filter-label"><?php echo bearsmith_search_icon( 'search' ); // Static trusted SVG. ?> All</span>
                        <span class="search-results__filter-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
                    </a>

                    <?php foreach ( $group_labels as $group_key => $group_label ) : ?>
                        <?php
                        $group_count  = $group_counts[ $group_key ] ?? 0;
                        $filter_class = 'search-results__filter' . ( $type === $group_key ? ' is-active' : '' );
                        ?>
                        <?php if ( 0 === $group_count ) : ?>
                            <?php // Empty groups are inert: same inner structure, but a span, no link. ?>
                            <span class="<?php echo esc_attr( $filter_class . ' is-disabled' ); ?>">
                                <span class="search-results__filter-label"><?php echo bearsmith_search_icon( $group_icons[ $group_key ] ); // Static trusted SVG. ?> <?php echo esc_html( $group_label ); ?></span>
                                <span class="search-results__filter-count">0</span>
                            </span>
                        <?php else : ?>
                            <?php $filter_url = add_query_arg( array( 's' => urlencode( $search_query ), 'type' => $group_key ), $base_url ); ?>
                            <a class="<?php echo esc_attr( $filter_class ); ?>" href="<?php echo esc_url( $filter_url ); ?>">
                                <span class="search-results__filter-label"><?php echo bearsmith_search_icon( $group_icons[ $group_key ] ); // Static trusted SVG. ?> <?php echo esc_html( $group_label ); ?></span>
                                <span class="search-results__filter-count"><?php echo esc_html( number_format_i18n( $group_count ) ); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <div class="search-results__main">

                <form class="search-results__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <input type="search" class="search-results__input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search members, teams, classes, pages&hellip;" />
                </form>

                <?php if ( $shown_total > 0 ) : ?>

                    <div class="search-results__groups">

                        <?php foreach ( $display_groups as $group ) : ?>
                            <?php
                            // Unlike the API, the page hides empty groups.
                            if ( 0 === $group['count'] ) {
                                continue;
                            }
                            ?>
                            <section class="search-results__group">
                                <header class="search-results__group-head">
                                    <?php echo bearsmith_search_icon( $group_icons[ $group['key'] ] ); // Static trusted SVG. ?><h2><?php echo esc_html( $group['label'] ); ?></h2><span class="search-results__group-count"><?php echo esc_html( number_format_i18n( $group['count'] ) ); ?></span>
                                </header>

                                <?php foreach ( $group['items'] as $item ) : ?>
                                    <?php
                                    /*
                                     * Lead visual, by item type:
                                     *  - member with headshot → thumbnail (alt="" — the
                                     *    adjacent title already names the person)
                                     *  - member without / team → initials
                                     *  - class → medal icon, pages group → scroll icon
                                     */
                                    switch ( $item['type'] ) {
                                        case 'member':
                                            $lead_html = ! empty( $item['photo'] )
                                                ? '<img src="' . esc_url( $item['photo'] ) . '" loading="lazy" alt="" />'
                                                : '<span class="omni-result__initials">' . esc_html( bearsmith_search_initials( $item['title'] ) ) . '</span>';
                                            break;
                                        case 'team':
                                            $lead_html = '<span class="omni-result__initials">' . esc_html( bearsmith_search_initials( $item['title'] ) ) . '</span>';
                                            break;
                                        case 'year':
                                            $lead_html = bearsmith_search_icon( 'medal' );
                                            break;
                                        default: // post / page / events.
                                            $lead_html = bearsmith_search_icon( 'scroll-text' );
                                            break;
                                    }
                                    ?>
                                    <a class="omni-result" href="<?php echo esc_url( $item['url'] ); ?>">
                                        <span class="omni-result__lead"><?php echo $lead_html; // Escaped piecewise above. ?></span>
                                        <span class="omni-result__body">
                                            <span class="omni-result__title"><?php echo esc_html( $item['title'] ); ?></span>
                                            <?php if ( '' !== $item['sub'] ) : ?>
                                                <span class="omni-result__sub"><?php echo esc_html( $item['sub'] ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>

                                <?php
                                // The "Show all" link targets the type-filtered view, so it is
                                // suppressed when that filter is already active (it would link
                                // to the current URL and show nothing new).
                                if ( $group['count'] > count( $group['items'] ) && $type !== $group['key'] ) :
                                    ?>
                                    <?php $more_url = add_query_arg( array( 's' => urlencode( $search_query ), 'type' => $group['key'] ), $base_url ); ?>
                                    <a class="search-results__more" href="<?php echo esc_url( $more_url ); ?>">Show all <?php echo esc_html( number_format_i18n( $group['count'] ) ); ?> <?php echo esc_html( strtolower( $group['label'] ) ); ?></a>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>

                    </div>

                <?php else : ?>

                    <?php // Hand-typed ?type= for a group with no matches; rail stays for navigation. ?>
                    <p class="search-results__empty">No matches for &ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;.</p>

                <?php endif; ?>

            </div>

        </div>

    <?php else : ?>

        <?php // No query / no matches at all: headline + form + message, no rail (specimen parity). ?>
        <form class="search-results__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" class="search-results__input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search members, teams, classes, pages&hellip;" />
        </form>

        <?php if ( $has_query ) : ?>
            <p class="search-results__empty">No matches for &ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;.</p>
        <?php else : ?>
            <p class="search-results__empty">Start typing to search members, teams, classes, and pages.</p>
        <?php endif; ?>

    <?php endif; ?>

</section>


<?php get_footer(); ?>
