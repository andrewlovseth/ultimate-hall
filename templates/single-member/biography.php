<?php

    $biography = get_field('biography');

?>

<section class="biography">

    <div class="copy p1">
        <?php echo $biography; ?>
    </div>

    <?php if(have_rows('contributions_and_service')): ?>
        <section class="contributions-and-services">
            <div class="section-header">
                <h3 class="small">Contributions & Services</h3>
            </div>

            <ul>
                <?php while(have_rows('contributions_and_service')): the_row(); ?>
                
                    <li>
                        <?php if(get_sub_field('year')): ?><span class="year"><?php the_sub_field('year'); ?>:</span><?php endif; ?>
                        <span class="description"><?php the_sub_field('description'); ?></span>
                    </li>

                <?php endwhile; ?>
            </ul>
        </section>
    <?php endif; ?>
    

</section>