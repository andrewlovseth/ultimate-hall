<?php

/*
 * Hall of Fame Class Block Template
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'hall-class-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'block-hall-class class grid';
if( !empty($block['className']) ) {
    $className .= ' ' . $block['className'];
}
if( !empty($block['align']) ) {
    $className .= ' align' . $block['align'];
}
if( $is_preview ) {
    $className .= ' is-admin';
}

$year_obj = get_field('year');
$year = $year_obj->post_name;
$year_ID = $year_obj->ID;

?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
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
                        'value'		=> $year_ID,
                    ),
                )
            );
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <?php 
                $args = [
                    'member_ID' => $query->post->ID,
                ];
                get_template_part('template-parts/global/member', null, $args);
            ?>

        <?php endwhile; endif; wp_reset_postdata(); ?>

    </div>
</section>