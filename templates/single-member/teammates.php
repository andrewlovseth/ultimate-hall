<?php
    $type = get_field('meta_induction_type');
    $championships = get_field('us_championships');
    if(isset($type['value']) && $type['value'] == 'player' && !empty($championships)): ?>

    <?php
        // Init teammates array
        $teammates = array();

        // Build arrays of tournaments and teams for the current member
        $tournaments = array();
        $tournament_ids = array();

        if($championships) {
            foreach($championships as $championship) {
                $event = $championship['tournament'];
                $team = $championship['team'];

                // Extract tournament ID if it's a WP_Post object
                $event_id = is_object($event) ? $event->ID : $event;
                $team_id = is_object($team) ? $team->ID : $team;

                $tournaments[] = array('event' => $event_id, 'team' => $team_id);

                // Collect unique tournament IDs for querying
                if ($event_id && !in_array($event_id, $tournament_ids)) {
                    $tournament_ids[] = $event_id;
                }
            }
        }

        if(!empty($tournament_ids) && !empty($tournaments)) {
            // Build meta_query to find members who participated in ANY of these tournaments
            // This dramatically reduces the search space from ALL members to only relevant ones
            $meta_query = array('relation' => 'OR');

            foreach($tournament_ids as $tournament_id) {
                $meta_query[] = array(
                    'key'     => 'us_championships_$_tournament',
                    'value'   => $tournament_id,
                    'compare' => '='
                );
            }

            // Apply filter to support ACF repeater field wildcards
            $filter = bearsmith_modify_repeater_meta_query('us_championships');
            add_filter('posts_where', $filter);

            // Query only members who participated in any of the relevant tournaments
            $potential_teammates_query = new WP_Query(array(
                'post_type'      => 'member',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__not_in'   => array(get_the_ID()), // Exclude current member
                'meta_query'     => $meta_query
            ));

            // Remove filter after query
            remove_filter('posts_where', $filter);

            // Now check which of these potential teammates were on the same team
            if ($potential_teammates_query->have_posts()) {
                foreach($potential_teammates_query->posts as $member_id) {
                    $member_championships = get_field('us_championships', $member_id);

                    if($member_championships) {
                        // Check if this member shares any tournament/team combination
                        foreach($tournaments as $tournament) {
                            foreach($member_championships as $member_championship) {
                                // Extract IDs if they're WP_Post objects
                                $member_event = $member_championship['tournament'];
                                $member_team = $member_championship['team'];
                                $member_event_id = is_object($member_event) ? $member_event->ID : $member_event;
                                $member_team_id = is_object($member_team) ? $member_team->ID : $member_team;

                                if ($tournament['event'] == $member_event_id
                                    && $tournament['team'] == $member_team_id) {
                                    $teammates[] = $member_id;
                                    // Break both loops once we find a match for this member
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Ensure unique teammate IDs (shouldn't be necessary with break 2, but safety first)
        $teammates = array_unique($teammates);
        // Reindex to avoid WP treating an empty array unexpectedly
        $teammates = array_values($teammates);
        $first_name = get_field('vitals_first_name');

        if ( !empty($teammates) ) {
            $args = array(
                'post_type' => 'member',
                'posts_per_page' => -1,
                'post__in' => $teammates
            );
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) : ?>

            <section class="teammates grid">
                <div class="section-header align-center">
                    <h3><?php if($first_name):?><?php echo $first_name; ?>'s <?php endif; ?>Teammates</h3>
                </div>

                <div class="member-grid">            
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php get_template_part('template-parts/global/member'); ?>
                    <?php endwhile; ?>
                </div>
            </section>

    <?php endif; wp_reset_postdata(); } ?>

<?php endif; ?>