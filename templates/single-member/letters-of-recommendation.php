<?php if(get_field('show_letters')): ?>
    <?php if(have_rows('letters_of_recommendation')): ?>

        <section class="letters-of-recommendation grid">
            <div class="section-header align-center">
                <h3>Letters of Recommendation</h3>
            </div>

            <?php while(have_rows('letters_of_recommendation')) : the_row(); ?>

                <?php if( get_row_layout() == 'letter' ): ?>

                    <div class="letter">
                        <div class="copy p2 extended">
                            <?php echo get_sub_field('text'); ?>
                        </div>		
                    </div>

                <?php endif; ?>

            <?php endwhile; ?>
        </section>

    <?php endif; ?>
<?php endif; ?>