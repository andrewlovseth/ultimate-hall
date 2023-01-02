<?php 

$template = get_field('template');

if(have_rows('entries')): ?>
    
    <section class="entries entries-<?php echo $template; ?> grid">
        <div class="entries-<?php echo $template; ?>__gallery">
            <?php while(have_rows('entries')) : the_row(); ?>

                <?php if( get_row_layout() == 'entry' ): ?>

                    <?php if($template == 'media'): ?>

                        <?php get_template_part('templates/special-merit/entry-media'); ?>

                    <?php else: ?>
                        
                        <?php get_template_part('templates/special-merit/entry-default'); ?>

                    <?php endif; ?>

                <?php endif; ?>
                
            <?php endwhile; ?>
        </div>
    </section>

<?php endif; ?>