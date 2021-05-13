<?php if(have_rows('awards')): ?>
    <div class="vitals-section awards">
        <div class="vitals-header">
            <h3>Awards</h3>
        </div>

        <?php while(have_rows('awards')): the_row(); ?>

            <p>
                <span class="award"><?php the_sub_field('award'); ?></span>
                <?php if(get_sub_field('year')): ?><span class="year">(<?php $year = get_sub_field('year'); echo $year->post_title; ?>)</span><?php endif; ?>
            </p>

        <?php endwhile; ?>
    </div>
<?php endif; ?>