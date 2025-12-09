<?php $classes = get_field('classes', 'options'); if( $classes ): ?>
    <?php foreach( $classes as $class ): ?>
        <?php
            $year = $class->post_title;
            $class_ID = $class->ID;
        ?>
        
        <section class="class grid" id="class-<?php echo $year; ?>">
            <div class="section-header align-center">
                <h3><?php echo inaugural_get_class_title($year); ?></h3>
            </div>

            <div class="member-grid">

                <?php
                    $query = bearsmith_get_members_by_class($class_ID);
                    if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                    <?php get_template_part('template-parts/global/member'); ?>


                <?php endwhile; endif; wp_reset_postdata(); ?>

            </div>
        </section>

    <?php endforeach; ?>
<?php endif; ?>