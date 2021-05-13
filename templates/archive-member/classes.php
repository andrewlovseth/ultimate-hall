<?php $classes = get_field('classes', 'options'); if( $classes ): ?>
    <?php foreach( $classes as $class ): ?>
        <?php
            $year = $class->post_title;
            $class_ID = $class->ID;
        ?>
        
        <section class="class grid" id="class-<?php echo $year; ?>">
            <div class="section-header align-center">
                <h3>Class of <?php echo $year; ?></h3>
            </div>

            <div class="member-grid">

                <?php
                    $args = array(
                        'post_type' => 'member',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                        'meta_query' => array(
                            array(
                                'key'		=> 'meta_class',
                                'compare'	=> '=',
                                'value'		=> $class_ID,
                            ),
                        )
                    );
                    $query = new WP_Query( $args );
                    if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                    <?php get_template_part('template-parts/global/member'); ?>


                <?php endwhile; endif; wp_reset_postdata(); ?>

            </div>
        </section>

    <?php endforeach; ?>
<?php endif; ?>