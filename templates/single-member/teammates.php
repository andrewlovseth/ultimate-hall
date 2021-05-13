<?php
    $type = get_field('meta_induction_type');
    if($type['value'] == 'player'): ?>

    <?php
        // Init teammates array
        $teammates = array();
        
        // Build array of tournaments
        $tournaments = array();
        $championships = get_field('us_championships');
        if($championships) {
            foreach($championships as $championship) {
                $event = $championship['tournament'];
                $team = $championship['team'];
                array_push($tournaments, array('event' => $event, 'team' => $team));
            }
        }

        // Build array of all members [IDs]
        $member_args = array(
            'numberposts' => -1,
            'post_type' => 'member',
            'fields' => 'ids'
        );
        $members = get_posts($member_args);
        
        if($tournaments) {

            // Query over all tournaments
            foreach($tournaments as $tournament) {

                // Query over all members
                foreach($members as $member) {
                    $us_championships = get_field('us_championships', $member);
                    if($us_championships) {

                        // Query that members list of tournaments
                        foreach($us_championships as $us_championship) {

                            // Query if member was at same tournament and on same team
                            if ($tournament['event'] == $us_championship['tournament'] && $tournament['team'] == $us_championship['team']) {

                                // If so, add their ID to the teammates array
                                array_push($teammates, $member);
                            }
                        }
                    }
                }
            }
        }

        // Remove this member from array
        $teammates = array_diff($teammates, array( get_the_ID() ));
        $args = array(
            'post_type' => 'member',
            'posts_per_page' => -1,
            'post__in' => $teammates
        );
        $first_name = get_field('vitals_first_name');

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

    <?php endif; wp_reset_postdata(); ?>

<?php endif; ?>