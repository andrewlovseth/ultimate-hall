<?php
    // Pre-check for members in this year and redirect if none exist
    $year_id = get_the_ID();
    $query = bearsmith_get_members_by_class($year_id);
    if (!$query->have_posts()) {
        wp_safe_redirect(get_post_type_archive_link('member'), 302);
        exit;
    }
?>

<?php get_header(); ?>

    <?php
        // Use unified page header with custom class title
        $year_title = get_post_field('post_title', get_the_ID());
        get_template_part('template-parts/global/page-header-unified', null, array(
            'title' => inaugural_get_class_title($year_title)
        ));
    ?>

    <section class="class grid">
        <div class="member-grid">

            <?php
                if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); ?>

                    <?php get_template_part('template-parts/global/member'); ?>

                <?php endwhile; endif; wp_reset_postdata(); ?>
        
        </div>
    </section>

<?php get_footer(); ?>