<?php

    $biography = get_field('biography');
    $photos = get_field('photos');
    $player_card = $photos['player_card'];

    $induction_speech = get_field('induction_speech');

?>

<section class="biography">

    <div class="copy p1">
        <?php if($player_card): ?>
            <div class="player-card">
                <?php echo wp_get_attachment_image($player_card['ID'], 'full'); ?>
            </div>
        <?php endif; ?>

        <?php echo $biography; ?>
    </div>

    <?php if(have_rows('contributions_and_service')): ?>
        <section class="contributions-and-service">
            <div class="section-header">
                <h3 class="small">Contributions & Service</h3>
            </div>

            <ul>
                <?php while(have_rows('contributions_and_service')): the_row(); ?>
                
                    <li>
                        <?php if(get_sub_field('year')): ?><span class="year"><?php echo get_sub_field('year'); ?>:</span><?php endif; ?>
                        <span class="description"><?php echo get_sub_field('description'); ?></span>
                    </li>

                <?php endwhile; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if($induction_speech): ?>
        <section class="induction-speech">
            <div class="section-header">
                <h3 class="small">Induction Speech</h3>
            </div>

            <div class="cta">
                <a href="<?php echo $induction_speech['url']; ?>" class="btn btn__charcoal">Read <?php the_title(); ?>'s Induction Speech</a>
            </div>

        </section>

    <?php endif; ?>
    

</section>