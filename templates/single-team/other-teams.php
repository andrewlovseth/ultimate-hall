<?php

    $division_obj = get_field('division');
    if($division_obj) {
        $division = $division_obj[0]->post_title;
        $division_id = $division_obj[0]->ID;
    }

    if($division):
?>

    <section class="other-teams grid">
        <div class="section-header align-center">
            <h3><?php echo $division; ?> Teams</h3>
        </div>

        <div class="teams-list">
            <?php
                $args = bearsmith_default_query_args('team', array(
                    'meta_query' => array(
                        array(
                            'key' => 'division', // name of custom field
                            'value' => '"' . $division_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                            'compare' => 'LIKE'
                        )
                    )
                ));
                $query = new WP_Query( $args );
                if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                <?php if(get_field('national_team') == FALSE): ?>

                    <div class="team">
                        <a href="<?php the_permalink(); ?>">
                            <span class="name"><?php the_title(); ?></span>
                            <span class="location"><?php echo get_field('city'); ?></span>
                        </a>

                    </div>

                <?php endif; ?>

            <?php endwhile; endif; wp_reset_postdata(); ?>

        </div>




    
    </section>

<?php endif; ?>