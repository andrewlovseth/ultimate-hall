<?php

    $vitals = get_field('vitals');
    $hometown = $vitals['hometown'];
    $birthdate_field = $vitals['birthdate'];
    $birthdate = DateTime::createFromFormat('Ymd', $birthdate_field);

    $photos = get_field('photos');
    $headshot = $photos['headshot'];

    $meta = get_field('meta');
    $class = $meta['class'];
    $type = $meta['induction_type'];
?>

<section class="grid vitals">
    <div class="headshot photo">
        <img src="<?php echo $headshot['url']; ?>" alt="<?php echo $headshot['alt']; ?>" />
    </div>

    <div class="info">
        <div class="headline">
            <h1><?php the_title(); ?></h1>
        </div>

        <div class="copy p2">
            <p><strong>Inducted:</strong> <?php echo $class->post_name; ?> - <?php echo $type['label']; ?></p>
            <p><strong>Hometown:</strong> <?php echo $hometown; ?></p>
            <?php if($birthdate): ?>
                <p><strong>Birthdate:</strong> <?php echo $birthdate->format('M j, Y'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>