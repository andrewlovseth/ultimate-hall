<?php if(have_rows('interview')): ?>

    <section class="interview grid">
        <div class="section-header align-center">
            <h3>Interview</h3>
        </div>

        <?php while(have_rows('interview')) : the_row(); ?>

            <?php if( get_row_layout() == 'qa' ): ?>

                <div class="q-and-a">
                    <div class="question headline">
                        <h4><?php echo get_sub_field('question'); ?></h4>
                    </div>
                    <div class="answer copy p2">
                        <?php echo get_sub_field('answer'); ?>
                    </div>		
                </div>

            <?php endif; ?>

        <?php endwhile; ?>
    </section>

<?php endif; ?>