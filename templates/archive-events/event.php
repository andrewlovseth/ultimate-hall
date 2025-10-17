<?php

    $title = get_the_title();
    $location = get_field('location');
    $date = get_the_time('F j, Y');

?>

<div class="event">

    <div class="event__photo">
        <?php if ( has_post_thumbnail() ) : ?>
            <a class="event__photo-link" href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('full', ['class' => 'event__image']); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="event__info">
        <div class="event__headline">
            <h3 class="event__title">
                <a class="event__title-link" href="<?php the_permalink(); ?>">
                    <?php echo $title; ?>
                </a>
            </h3>
        </div>

        <div class="event__meta">
            <?php if ($location): ?>
                <p class="event__location"><?php echo $location; ?></p>
            <?php endif; ?>

            <?php if ($date): ?>
                <p class="event__date"><?php echo $date; ?></p>
            <?php endif; ?>
        </div>
    </div>

</div>