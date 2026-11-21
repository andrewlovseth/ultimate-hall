<section class="members grid">
    <div class="member-grid">
        <?php
            $query = new WP_Query( array( 'post_type' => 'member', 'post__in' => uh_members_for_team( get_the_ID() ) ?: array( 0 ), 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => -1 ) );
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
                get_template_part( 'template-parts/global/member' );
            endwhile; endif; wp_reset_postdata();
        ?>
    </div>
</section>
