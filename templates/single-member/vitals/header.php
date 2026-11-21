<?php

    $vitals = get_field('vitals') ?: array();
    $hometown = $vitals['hometown'] ?? '';
    $birthdate_field = $vitals['birthdate'] ?? '';
    $birthdate = $birthdate_field ? DateTime::createFromFormat('Ymd', $birthdate_field) : false;

    $date_of_death_field = $vitals['date_of_death'] ?? '';
    $date_of_death = $date_of_death_field ? DateTime::createFromFormat('Ymd', $date_of_death_field) : false;

    $photos = get_field('photos') ?: array();
    $headshot = $photos['headshot'] ?? null;

    $age = $birthdate ? ( new DateTime() )->diff($birthdate) : false;

    $lifespan = ( $birthdate && $date_of_death ) ? $date_of_death->diff($birthdate) : false;

?>

<div class="header">
    <?php if($headshot): ?>
        <div class="photo">
            <?php echo wp_get_attachment_image( $headshot['ID'], 'large', false, array( 'sizes' => '230px', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
        </div>
    <?php endif; ?>

    <div class="name">
        <h2><?php the_title(); ?></h2>
    </div>

    <?php if($hometown): ?>
        <div class="hometown vital">
            <p><strong>Hometown:</strong> <?php echo esc_html( $hometown ); ?></p>
        </div>
    <?php endif; ?>

    <?php if($birthdate): ?>
        <?php if($lifespan): ?>
            <div class="birthdate vital">
                <p>
                    <strong>Born:</strong> <?php echo $birthdate->format('Y'); ?><br/>
                    <strong>Died:</strong> <?php echo $date_of_death->format('Y'); ?> (Age <?php echo $lifespan->y; ?>)<br/>
                </p>
            </div>
        <?php else: ?>
            <div class="birthdate vital">
                <p><strong>Born:</strong> <?php echo $birthdate->format('Y'); ?> (Age <?php echo $age->y; ?>)</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>