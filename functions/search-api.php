<?php
/**
 * Global Search API
 *
 * Powers the omnibox typeahead dropdown and the /?s= search results page.
 *
 * Architecture: bearsmith_grouped_search() is the single source of truth for
 * grouped search across members, teams, classes (year CPT), and the mixed
 * pages group (post/page/events). The REST endpoint below is a thin JSON
 * wrapper around it; search.php calls it directly for server rendering.
 * Tournaments are deliberately excluded from search.
 *
 * Route: GET /wp-json/ultimatehall/v1/search?q=<term>&per_group=<int>&group=<key>
 */

/**
 * Run a grouped search across the site's main content types.
 *
 * Entity groups (members, teams, classes) match against post_title ONLY via
 * the `search_columns` WP_Query arg (WP 6.2+). The omnibox is a typeahead
 * against names; WordPress' default `s` behavior also matches content and
 * excerpt, which is too noisy for entities. The mixed "pages" group keeps the
 * default full-text behavior on purpose — finding a blog post by a word in
 * its body is expected there.
 *
 * @param string      $q          Search term. Trimmed here; sanitize before calling.
 * @param int         $per_group  Max items per group, clamped 1-50. Counts always reflect totals.
 * @param string|null $only_group Optional. Restrict to one group: members|teams|classes|pages.
 *                                Invalid values are ignored (all groups returned).
 * @return array[] Ordered list of groups: { key, label, count, items[] }.
 *                 Returns an empty array when $q is shorter than 2 characters.
 */
function bearsmith_grouped_search( $q, $per_group = 5, $only_group = null ) {
    $q         = trim( (string) $q );
    $per_group = max( 1, min( 50, (int) $per_group ) );

    // Sub-2-character terms would match nearly everything; treat as "no search".
    if ( mb_strlen( $q ) < 2 ) {
        return array();
    }

    // Insertion order here defines response order: members, teams, classes, pages.
    $group_defs = array(
        'members' => array(
            'label' => 'Members',
            'args'  => array(
                'post_type'      => 'member',
                'search_columns' => array( 'post_title' ),
            ),
        ),
        'teams'   => array(
            'label' => 'Teams',
            'args'  => array(
                'post_type'      => 'team',
                'search_columns' => array( 'post_title' ),
            ),
        ),
        'classes' => array(
            'label' => 'Classes',
            'args'  => array(
                'post_type'      => 'year',
                'search_columns' => array( 'post_title' ),
            ),
        ),
        'pages'   => array(
            'label' => 'Pages',
            'args'  => array(
                // No search_columns: full title + content + excerpt matching.
                'post_type' => array( 'post', 'page', 'events' ),
            ),
        ),
    );

    if ( null !== $only_group && isset( $group_defs[ $only_group ] ) ) {
        $group_defs = array( $only_group => $group_defs[ $only_group ] );
    }

    $groups = array();

    foreach ( $group_defs as $key => $def ) {
        $query_args = array_merge(
            array(
                's'                   => $q,
                'post_status'         => 'publish',
                'posts_per_page'      => $per_group,
                'ignore_sticky_posts' => true,
                // No no_found_rows: found_posts is needed for group counts.
            ),
            $def['args']
        );

        // Default orderby is relevance when `s` is set — keep it.
        $query = new WP_Query( $query_args );

        $items = array();
        foreach ( $query->posts as $post ) {
            $items[] = bearsmith_search_format_item( $post );
        }

        $groups[] = array(
            'key'   => $key,
            'label' => $def['label'],
            'count' => (int) $query->found_posts,
            'items' => $items,
        );
    }

    return $groups;
}

/**
 * Format a single post into the shared search-result item shape.
 *
 * Sub-line pieces are joined with " · " (interpunct), skipping empties.
 * Entity sub-lines do not repeat the group name; items in the mixed "pages"
 * group lead with their kind (News / Page / Event) since the group header
 * alone can't identify them.
 *
 * The `photo` key is always present: a thumbnail URL for members with a
 * headshot, null for everything else (teams/classes/pages render initials
 * or icons client-side instead).
 *
 * @param WP_Post $post The matched post.
 * @return array { id, type, title, url, sub, photo }.
 */
function bearsmith_search_format_item( $post ) {
    $title     = get_the_title( $post );
    $sub_parts = array();
    $photo     = null;

    switch ( $post->post_type ) {
        case 'member':
            $photo = bearsmith_search_member_photo( $post->ID );

            // e.g. "Player · Class of 2010 · Open"
            $sub_parts[] = bearsmith_search_choice_label( get_field( 'meta_induction_type', $post->ID ) );

            $class_title = bearsmith_search_year_title( get_field( 'meta_class', $post->ID ) );
            if ( $class_title ) {
                $sub_parts[] = 'Class of ' . $class_title;
            }

            $sub_parts[] = bearsmith_search_choice_label( get_field( 'meta_induction_division', $post->ID ) );
            break;

        case 'team':
            // e.g. "Seattle · Open/Mixed · National Team"
            $sub_parts[] = get_field( 'city', $post->ID );

            $division_names = array();
            $divisions      = get_field( 'division', $post->ID );
            if ( is_array( $divisions ) ) {
                // Relationship field: rows may be WP_Post objects or raw IDs.
                foreach ( $divisions as $division ) {
                    if ( $division instanceof WP_Post ) {
                        $division_names[] = $division->post_title;
                    } elseif ( is_numeric( $division ) ) {
                        $division_names[] = get_the_title( (int) $division );
                    }
                }
            }
            $division_names = array_filter( $division_names );
            if ( ! empty( $division_names ) ) {
                $sub_parts[] = implode( '/', $division_names );
            }

            if ( get_field( 'national_team', $post->ID ) ) {
                $sub_parts[] = 'National Team';
            }
            break;

        case 'year':
            // Year titles are bare years ("2024"); present as "Class of 2024".
            $title = 'Class of ' . $title;

            // Cheap count-only query: 1 row fetched, found_posts gives the total.
            $count_query = bearsmith_get_members_by_class(
                $post->ID,
                array(
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                )
            );
            $inductees   = (int) $count_query->found_posts;
            $sub_parts[] = sprintf( '%d %s', $inductees, 1 === $inductees ? 'inductee' : 'inductees' );
            break;

        case 'post':
            $sub_parts[] = 'News';
            $sub_parts[] = get_the_date( '', $post );
            break;

        case 'page':
            $sub_parts[] = 'Page';
            break;

        case 'events':
            $sub_parts[] = 'Event';
            $sub_parts[] = get_field( 'location', $post->ID ); // Plain text field.
            break;
    }

    // Drop empty pieces so we never render stray separators.
    $sub_parts = array_filter(
        $sub_parts,
        function ( $part ) {
            return is_string( $part ) && '' !== trim( $part );
        }
    );

    return array(
        'id'    => $post->ID,
        'type'  => $post->post_type,
        'title' => $title,
        'url'   => get_permalink( $post ),
        'sub'   => implode( ' · ', $sub_parts ),
        'photo' => $photo,
    );
}

/**
 * Resolve a member's headshot to a thumbnail-size URL.
 *
 * Mirrors template-parts/global/member.php: special-merit members store
 * their portrait in `introduction_photo`; everyone else in `photos_headshot`.
 * ACF image fields can return an array, attachment ID, or URL string
 * depending on the field's return format — all three are handled so a
 * field-config change can't silently break search.
 *
 * @param int $post_id Member post ID.
 * @return string|null Thumbnail URL, or null when no photo is set.
 */
function bearsmith_search_member_photo( $post_id ) {
    $field = ( 'templates/special-merit.php' === get_page_template_slug( $post_id ) )
        ? 'introduction_photo'
        : 'photos_headshot';

    $image = get_field( $field, $post_id );

    if ( is_array( $image ) ) {
        if ( ! empty( $image['sizes']['thumbnail'] ) ) {
            return (string) $image['sizes']['thumbnail'];
        }
        // Image smaller than the thumbnail size has no resized version.
        return ! empty( $image['url'] ) ? (string) $image['url'] : null;
    }

    if ( is_numeric( $image ) ) {
        $url = wp_get_attachment_image_url( (int) $image, 'thumbnail' );
        return $url ? $url : null;
    }

    if ( is_string( $image ) && '' !== $image ) {
        return $image;
    }

    return null;
}

/**
 * Initials for results without a photo (members missing a headshot, teams).
 *
 * First letter of each of the first two words, uppercased. Leading
 * punctuation is stripped per word so nicknames in quotes contribute their
 * letter ('Alex "Dutchy" Ghesquiere' → "AD", not "A\"").
 *
 * @param string $title Result title.
 * @return string 1-2 uppercase characters, or "?" when nothing usable.
 */
function bearsmith_search_initials( $title ) {
    $words   = preg_split( '/\s+/', trim( (string) $title ) );
    $letters = array();

    foreach ( $words as $word ) {
        $word = preg_replace( '/^[^\p{L}\p{N}]+/u', '', $word );
        if ( '' === $word ) {
            continue;
        }
        $letters[] = mb_strtoupper( mb_substr( $word, 0, 1 ) );
        if ( 2 === count( $letters ) ) {
            break;
        }
    }

    return empty( $letters ) ? '?' : implode( '', $letters );
}

/**
 * Inline SVG for the search UI's "archival" icon set (Lucide v0.544.0,
 * ISC license), rendered at stroke 1.5 for the engraved feel approved in
 * the search-results-refine specimen.
 *
 * Safe to echo without further escaping: the markup is assembled entirely
 * from this static, hardcoded map — no user input or post data is ever
 * interpolated. Unknown names return an empty string.
 *
 * @param string $name Icon name: search|user-round|flag|medal|scroll-text.
 * @return string SVG markup, or '' for unknown names.
 */
function bearsmith_search_icon( $name ) {
    static $paths = array(
        // All filter / search affordance.
        'search'      => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
        // Members.
        'user-round'  => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        // Teams.
        'flag'        => '<path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 7.333 2q2 0 3.067-.8A1 1 0 0 1 20 4v10a1 1 0 0 1-.4.8A6 6 0 0 1 16 16c-3 0-5-2-8-2a6 6 0 0 0-4 1.528"/>',
        // Classes.
        'medal'       => '<path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/><path d="M12 18v-2h-.5"/>',
        // Pages (news, pages, events).
        'scroll-text' => '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>',
    );

    if ( ! isset( $paths[ $name ] ) ) {
        return '';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"'
        . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $paths[ $name ]
        . '</svg>';
}

/**
 * Extract the human label from an ACF choice field (button_group / select).
 *
 * These fields return array{value,label} when "Both" return format is set,
 * but a plain string when the format is "Value" or "Label" — handle both.
 *
 * @param mixed $value Raw get_field() return.
 * @return string|null Label string, or null when unset.
 */
function bearsmith_search_choice_label( $value ) {
    if ( is_array( $value ) && isset( $value['label'] ) && '' !== $value['label'] ) {
        return (string) $value['label'];
    }
    if ( is_string( $value ) && '' !== $value ) {
        return $value;
    }
    return null;
}

/**
 * Resolve an ACF post_object value (WP_Post or ID) to its post title.
 *
 * @param mixed $value Raw get_field() return for a post_object field.
 * @return string|null Post title, or null when unset/unresolvable.
 */
function bearsmith_search_year_title( $value ) {
    if ( $value instanceof WP_Post ) {
        return $value->post_title;
    }
    if ( is_numeric( $value ) ) {
        $year_title = get_the_title( (int) $value );
        return '' !== $year_title ? $year_title : null;
    }
    return null;
}

/**
 * Register the public search REST route.
 *
 * permission_callback is __return_true because every result is public,
 * published content — the same data anyone can reach by browsing the site.
 */
function bearsmith_register_search_route() {
    register_rest_route(
        'ultimatehall/v1',
        '/search',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'bearsmith_search_rest_callback',
            'permission_callback' => '__return_true',
            'args'                => array(
                'q'         => array(
                    'required'          => true,
                    'type'              => 'string',
                    'description'       => 'Search term (minimum 2 characters for results).',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'per_group' => array(
                    'type'              => 'integer',
                    'default'           => 5,
                    'description'       => 'Max items per group, clamped 1-50.',
                    'sanitize_callback' => 'absint',
                ),
                'group'     => array(
                    'type'        => 'string',
                    'description' => 'Restrict results to a single group.',
                    'enum'        => array( 'members', 'teams', 'classes', 'pages' ),
                ),
            ),
        )
    );
}
add_action( 'rest_api_init', 'bearsmith_register_search_route' );

/**
 * REST callback: wraps bearsmith_grouped_search() in the response envelope.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response { query, total, groups }.
 */
function bearsmith_search_rest_callback( WP_REST_Request $request ) {
    $q         = trim( (string) $request->get_param( 'q' ) );
    $per_group = (int) $request->get_param( 'per_group' );
    $group     = $request->get_param( 'group' );

    // Short query: valid request, empty result set (contract: 200, groups []).
    if ( mb_strlen( $q ) < 2 ) {
        return rest_ensure_response(
            array(
                'query'  => $q,
                'total'  => 0,
                'groups' => array(),
            )
        );
    }

    $groups = bearsmith_grouped_search( $q, $per_group, $group ? $group : null );

    $total = 0;
    foreach ( $groups as $group_data ) {
        $total += $group_data['count'];
    }

    return rest_ensure_response(
        array(
            'query'  => $q,
            'total'  => $total,
            'groups' => $groups,
        )
    );
}
