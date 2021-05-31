<?php $image = get_field('hero_image'); if( $image ): ?>
    <section class="hero-image">
        <div class="content">
            <?php echo wp_get_attachment_image($image['ID'], 'full'); ?>
        </div>
    </section>
<?php endif; ?>
