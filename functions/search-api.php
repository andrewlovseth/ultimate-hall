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
 * Meta key holding a member's flattened "individual names" search index.
 *
 * Underscore-prefixed so WP hides it from the Custom Fields metabox. Holds a
 * single space-joined string of every `entries` row's "First Last" — and ONLY
 * those names, never the post_title. The title is matched separately in SQL
 * (see bearsmith_member_search_clauses), so a later Quick Edit to the title
 * can't desync this index.
 */
const BEARSMITH_SEARCH_INDEX_META = '_bearsmith_search_index';

/**
 * Build (and persist) a member's individual-names search index.
 *
 * Group inductees (e.g. "The MOB") store their people in the `entries`
 * flexible-content field; each `entry` row has a `vitals` group with
 * first_name / last_name (nicknames live inside first_name, e.g.
 * 'David "Blues"'). This flattens every row to "First Last" and saves the
 * space-joined result so a LIKE query can find an individual by name and
 * surface the GROUP post (individuals have no page of their own).
 *
 * Regular members have no `entries`; they get an empty string so the LEFT
 * JOIN still finds a row to compare against (and falls through to title-only
 * matching). Idempotent — safe to call repeatedly (save hook + backfill).
 *
 * @param int $post_id Member post ID.
 * @return string The index string saved to meta (may be empty).
 */
function bearsmith_build_member_search_index( $post_id ) {
    $post_id = (int) $post_id;
    $names   = array();

    $rows = get_field( 'entries', $post_id );
    if ( is_array( $rows ) ) {
        foreach ( $rows as $row ) {
            // Defensive: only the `entry` layout carries a vitals group.
            if ( ! is_array( $row ) || empty( $row['vitals'] ) || ! is_array( $row['vitals'] ) ) {
                continue;
            }

            $first = isset( $row['vitals']['first_name'] ) ? trim( (string) $row['vitals']['first_name'] ) : '';
            $last  = isset( $row['vitals']['last_name'] ) ? trim( (string) $row['vitals']['last_name'] ) : '';

            $full = trim( $first . ' ' . $last );
            if ( '' !== $full ) {
                $names[] = $full;
            }
        }
    }

    $index = implode( ' ', $names );

    update_post_meta( $post_id, BEARSMITH_SEARCH_INDEX_META, $index );

    return $index;
}

/**
 * Keep the search index fresh whenever a member is saved.
 *
 * Priority 20 runs after ACF (priority 10) has written the `entries` rows to
 * meta, so get_field() inside the builder reads the just-saved values. Quick
 * Edit only touches the title (not `entries`), so acf/save_post is sufficient
 * — title matching is handled separately in SQL and never depends on this.
 *
 * @param int|string $post_id Post ID being saved (ACF passes "options" etc. for non-posts).
 * @return void
 */
function bearsmith_refresh_member_search_index( $post_id ) {
    // ACF fires this for options pages too; bail unless it's a real member post.
    if ( ! is_numeric( $post_id ) ) {
        return;
    }
    if ( 'member' !== get_post_type( (int) $post_id ) ) {
        return;
    }

    bearsmith_build_member_search_index( (int) $post_id );
}
add_action( 'acf/save_post', 'bearsmith_refresh_member_search_index', 20 );

/**
 * Build scoped posts_join + posts_where filters for the members search query.
 *
 * Mirrors the add_filter/remove_filter-around-the-query idiom of
 * bearsmith_modify_repeater_meta_query(). The members query must match
 * post_title OR the _bearsmith_search_index meta — in ONE query so found_posts
 * (the count badge) and the per-group cap stay correct.
 *
 * Approach: LEFT JOIN postmeta on the index key (LEFT, not INNER, so members
 * with no index row still match on title), then AND a
 * ( post_title LIKE … OR meta_value LIKE … ) group onto the WHERE. Returns
 * both closures keyed 'join' and 'where'; the caller adds/removes each on its
 * respective hook. Duplicate rows are prevented with a GROUP BY on the posts
 * filter (see the 'groupby' closure) — a member can have at most one index
 * meta row, but GROUP BY is a cheap guarantee against any future fan-out.
 *
 * @param string $q Raw (already-trimmed) search term.
 * @return array{join:callable,where:callable,groupby:callable}
 */
function bearsmith_member_search_clauses( $q ) {
    global $wpdb;

    // Escape the LIKE term once; wrap with % wildcards for a substring match.
    $like = '%' . $wpdb->esc_like( (string) $q ) . '%';

    return array(
        'join'    => function ( $join ) use ( $wpdb ) {
            // Alias the join so multiple filters can't collide on table name.
            $meta_key = BEARSMITH_SEARCH_INDEX_META;
            $join    .= $wpdb->prepare(
                " LEFT JOIN {$wpdb->postmeta} AS bs_idx"
                . " ON ( bs_idx.post_id = {$wpdb->posts}.ID AND bs_idx.meta_key = %s )",
                $meta_key
            );
            return $join;
        },
        'where'   => function ( $where ) use ( $wpdb, $like ) {
            // post_title match OR index match. prepare() handles both LIKE values.
            $where .= $wpdb->prepare(
                " AND ( {$wpdb->posts}.post_title LIKE %s OR bs_idx.meta_value LIKE %s )",
                $like,
                $like
            );
            return $where;
        },
        'groupby' => function ( $groupby ) use ( $wpdb ) {
            // Collapse any duplicate rows the join could theoretically produce.
            $by = "{$wpdb->posts}.ID";
            if ( '' === trim( (string) $groupby ) ) {
                return $by;
            }
            return $groupby . ", {$by}";
        },
    );
}

/**
 * Run a grouped search across the site's main content types.
 *
 * Entity groups (teams, classes) match against post_title ONLY via the
 * `search_columns` WP_Query arg (WP 6.2+). The omnibox is a typeahead against
 * names; WordPress' default `s` behavior also matches content and excerpt,
 * which is too noisy for entities. The mixed "pages" group keeps the default
 * full-text behavior on purpose — finding a blog post by a word in its body
 * is expected there.
 *
 * Members are special: a single `member` post can be a GROUP inductee (e.g.
 * "The MOB") whose individual people live in the `entries` field. Those names
 * are flattened into the _bearsmith_search_index meta, and the members query
 * matches post_title OR that index via a scoped posts_join/where filter (see
 * bearsmith_member_search_clauses) — so searching an individual surfaces the
 * group post. Because that filter does the matching, the members group runs
 * WITHOUT `s`/`search_columns`.
 *
 * @param string      $q          Search term. Trimmed here; sanitize before calling.
 * @param int         $per_group  Max items per group, clamped 1-50. Counts always reflect totals.
 * @param string|null $only_group Optional. Restrict ITEMS to one group
 *                                (members|teams|classes|pages). All four group
 *                                COUNTS are always returned regardless, so the
 *                                scope pills stay stable when tabbing between
 *                                scopes. Invalid values are ignored.
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
                'post_type' => 'member',
                // No `s`/`search_columns`: title-OR-index matching is done by
                // the scoped SQL filter below (member_search => true).
            ),
            // Signals the loop to apply bearsmith_member_search_clauses().
            'member_search' => true,
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

    // A valid $only_group restricts which group returns ITEMS, but every group
    // still runs so its count is reported — otherwise the scope pills would lose
    // their counts the moment you tab into a single scope.
    $active_group = ( null !== $only_group && isset( $group_defs[ $only_group ] ) ) ? $only_group : null;

    $groups = array();

    foreach ( $group_defs as $key => $def ) {
        $is_member_search = ! empty( $def['member_search'] );
        $is_active        = ( null === $active_group ) || ( $key === $active_group );

        $base_args = array(
            'post_status'         => 'publish',
            // Active group returns up to $per_group items; the rest are
            // count-only (1 ID row) — found_posts still reflects the true total.
            'posts_per_page'      => $is_active ? $per_group : 1,
            'ignore_sticky_posts' => true,
            // No no_found_rows: found_posts is needed for group counts.
        );
        if ( ! $is_active ) {
            $base_args['fields'] = 'ids';
        }

        // The members group matches via the scoped SQL filter, not `s`. Every
        // other group keeps WordPress' relevance-ordered `s` search.
        if ( ! $is_member_search ) {
            $base_args['s'] = $q;
        }

        $query_args = array_merge( $base_args, $def['args'] );

        if ( $is_member_search ) {
            // Title-OR-index matching: add the scoped filters immediately
            // before the query and remove them immediately after, mirroring
            // bearsmith_modify_repeater_meta_query()'s usage idiom. Title order
            // (alphabetical) is fine here — there is no `s` relevance to honor.
            $query_args['orderby'] = 'title';
            $query_args['order']   = 'ASC';

            $clauses = bearsmith_member_search_clauses( $q );

            add_filter( 'posts_join', $clauses['join'] );
            add_filter( 'posts_where', $clauses['where'] );
            add_filter( 'posts_groupby', $clauses['groupby'] );

            $query = new WP_Query( $query_args );

            remove_filter( 'posts_join', $clauses['join'] );
            remove_filter( 'posts_where', $clauses['where'] );
            remove_filter( 'posts_groupby', $clauses['groupby'] );
        } else {
            // Default orderby is relevance when `s` is set — keep it.
            $query = new WP_Query( $query_args );
        }

        // Inactive (count-only) groups fetched IDs, not posts — skip formatting.
        $items = array();
        if ( $is_active ) {
            foreach ( $query->posts as $post ) {
                $items[] = bearsmith_search_format_item( $post, $q );
            }
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
 * When $query is supplied and a GROUP member matched on one of its `entries`
 * people (not its title), the sub-line shows "{induction_type} · {First Last}"
 * for the first matching individual (with " +N" when more than one matched),
 * so the row tells the user WHY the group surfaced.
 *
 * @param WP_Post $post  The matched post.
 * @param string  $query Optional. The trimmed search term, used to detect and
 *                       surface which individual inside a group matched.
 * @return array { id, type, title, url, sub, photo }.
 */
function bearsmith_search_format_item( $post, $query = '' ) {
    $title     = get_the_title( $post );
    $sub_parts = array();
    $photo     = null;

    switch ( $post->post_type ) {
        case 'member':
            $photo = bearsmith_search_member_photo( $post->ID );

            // If this group surfaced because an individual inside it matched
            // (and the group title itself did NOT), feature the PERSON: their
            // name becomes the title, with the group named underneath.
            $entry_match = bearsmith_search_member_entry_match( $post, $query );
            if ( null !== $entry_match ) {
                $title = $entry_match['name'];

                // Prefer the individual's own photo; fall back to the group logo
                // ($photo, set above) when the entry has none.
                if ( ! empty( $entry_match['photo'] ) ) {
                    $photo = $entry_match['photo'];
                }

                $type_label = bearsmith_search_choice_label( get_field( 'meta_induction_type', $post->ID ) );
                if ( null !== $type_label ) {
                    $sub_parts[] = $type_label;
                }
                $sub_parts[] = get_the_title( $post ); // the group they belong to
                if ( $entry_match['extra'] > 0 ) {
                    $sub_parts[] = '+' . $entry_match['extra'] . ' more';
                }
                break;
            }

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

    // get_the_title() runs wptexturize, which turns straight quotes into
    // smart-quote HTML entities (&#8220; …). Decode to real UTF-8 characters
    // here so every consumer escapes exactly once for its own context. Without
    // this the client's esc() and search.php's esc_html() re-escape the
    // ampersand, and the literal entity ("&#8220;") shows through.
    $title = html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
    $sub   = html_entity_decode( implode( ' · ', $sub_parts ), ENT_QUOTES, 'UTF-8' );

    return array(
        'id'    => $post->ID,
        'type'  => $post->post_type,
        'title' => $title,
        'url'   => get_permalink( $post ),
        'sub'   => $sub,
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

    return bearsmith_search_image_url( get_field( $field, $post_id ) );
}

/**
 * Resolve an ACF image field value to a thumbnail URL (or null).
 *
 * Handles all three ACF image return formats — array, attachment ID, or URL
 * string — so callers don't care how the field is configured. Prefers the
 * generated 'thumbnail' size, falling back to the full URL when an image is
 * smaller than the thumbnail size. Shared by the member headshot and the
 * per-individual entry photo.
 *
 * @param mixed $image ACF image value (array|int|string|false).
 * @return string|null Thumbnail URL, or null when absent/unresolvable.
 */
function bearsmith_search_image_url( $image ) {
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
 * Find the individual inside a GROUP member that matched the query.
 *
 * Returns null (so the caller renders the group normally — title = group name,
 * classification sub-line) when: no query, the post has no `entries`, the post
 * TITLE already contains the query (the group surfaced on its own name, e.g.
 * "MOB"), or no entry name matches.
 *
 * When one or more entry names match, returns
 * array( 'name' => '<first matching "First Last">', 'photo' => <url|null>,
 * 'extra' => <N others> ). The caller promotes 'name' to the result title (the
 * person), shows their 'photo' when present (falling back to the group logo),
 * and names the group underneath — e.g. searching "Blocker" on "The MOB" yields
 * a row titled 'David "Blues" Blocker' with sub 'Special Merit · The MOB'.
 *
 * Matching is case-insensitive and substring-based, mirroring the SQL LIKE that
 * surfaced the post, so the featured person always agrees with the hit.
 *
 * @param WP_Post $post  Member post.
 * @param string  $query Trimmed search term.
 * @return array{name:string,photo:?string,extra:int}|null Match info, or null for default rendering.
 */
function bearsmith_search_member_entry_match( $post, $query ) {
    $query = trim( (string) $query );
    if ( '' === $query ) {
        return null;
    }

    // If the title itself matched, the group surfaced on its own name — render
    // it as a normal group result, not as one of its individuals.
    if ( false !== mb_stripos( get_the_title( $post ), $query ) ) {
        return null;
    }

    $rows = get_field( 'entries', $post->ID );
    if ( ! is_array( $rows ) || empty( $rows ) ) {
        return null;
    }

    $matches = array();
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) || empty( $row['vitals'] ) || ! is_array( $row['vitals'] ) ) {
            continue;
        }

        $first = isset( $row['vitals']['first_name'] ) ? trim( (string) $row['vitals']['first_name'] ) : '';
        $last  = isset( $row['vitals']['last_name'] ) ? trim( (string) $row['vitals']['last_name'] ) : '';
        $full  = trim( $first . ' ' . $last );

        if ( '' !== $full && false !== mb_stripos( $full, $query ) ) {
            $matches[] = array(
                'name'  => $full,
                'photo' => isset( $row['vitals']['photo'] ) ? bearsmith_search_image_url( $row['vitals']['photo'] ) : null,
            );
        }
    }

    if ( empty( $matches ) ) {
        return null;
    }

    return array(
        'name'  => $matches[0]['name'],
        'photo' => $matches[0]['photo'],
        'extra' => count( $matches ) - 1,
    );
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
