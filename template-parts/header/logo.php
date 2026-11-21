<?php $image = get_field('header_logo', 'options'); ?>
<div class="logo">
    <a href="<?php echo esc_url( home_url('/') ); ?>"><img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?: get_bloginfo('name') ); ?>"<?php if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ): ?> width="<?php echo (int) $image['width']; ?>" height="<?php echo (int) $image['height']; ?>"<?php endif; ?> /></a>
</div>
