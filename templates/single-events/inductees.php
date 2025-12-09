<?php

    $classes = get_field('classes');

?>

<?php if ($classes): ?>
    <section class="inductees grid">
        <div class="section-header align-center">
            <h3>Inductees</h3>
        </div>

        <?php
            $class_ids = array_map(function($y) { return $y->ID; }, $classes);

            $args = bearsmith_default_query_args('member', array(
                'meta_query' => array(
                    array(
                        'key'       => 'meta_class',
                        'compare'   => 'IN',
                        'value'     => $class_ids,
                    ),
                ),
            ));

            $query = new WP_Query($args);
        ?>

        <?php if ($query->have_posts()): ?>
            <ul class="inductees-list">
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <?php
                        $type  = get_field('meta_induction_type');
                        $class = get_field('meta_class');
                    ?>
                    <li class="inductees-item">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        <?php if ($class): ?>
                            <span class="inductees-item__class inductees-item__meta"><?php echo esc_html($class->post_title); ?></span>
                        <?php endif; ?>

                        <?php if ($type): ?>
                            <span class="inductees-item__type inductees-item__meta"><?php echo esc_html($type['label']); ?></span>
                        <?php endif; ?>

                    </li>
                <?php endwhile; wp_reset_postdata(); ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>


