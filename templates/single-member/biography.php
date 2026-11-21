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
                <?php echo wp_get_attachment_image($player_card['ID'], 'full', false, array( 'loading' => 'lazy' )); ?>
            </div>
        <?php endif; ?>

        <?php echo wp_kses_post( $biography ); ?>
    </div>

    <?php $service = uh_member_service( get_the_ID() ); if ( $service ) : ?>
        <section class="contributions-and-service">
            <div class="section-header">
                <h3 class="small">Contributions & Service</h3>
            </div>

            <ul>
                <?php foreach ( $service as $entry ) : ?>
                    <li>
                        <?php if ( $entry['years'] ) : ?><span class="year"><?php echo esc_html( $entry['years'] ); ?>:</span><?php endif; ?>
                        <span class="description"><?php echo esc_html( $entry['description'] ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if($induction_speech): ?>
        <section class="induction-speech">
            <div class="section-header">
                <h3 class="small">Induction Speech</h3>
            </div>

            <div class="cta">
                <a href="<?php echo esc_url( $induction_speech['url'] ); ?>" class="btn btn__charcoal">Read <?php the_title(); ?>'s Induction Speech</a>
            </div>

        </section>

    <?php endif; ?>
    

</section>