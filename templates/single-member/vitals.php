<?php

    $vitals = get_field('vitals');
    $hometown = $vitals['hometown'];
    $birthdate_field = $vitals['birthdate'];
    $birthdate = DateTime::createFromFormat('Ymd', $birthdate_field);

    $photos = get_field('photos');
    $headshot = $photos['headshot'];
   
    $today = new DateTime();
    $birthday = new DateTime($birthdate_field);
    $age = $today->diff($birthday);

?>

<section class="vitals">
    <div class="photo">
        <img src="<?php echo $headshot['url']; ?>" alt="<?php echo $headshot['alt']; ?>" />
    </div>

    <div class="header">
        <div class="name">
            <h2><?php the_title(); ?></h2>
        </div>

        <?php if($hometown): ?>
            <div class="hometown vital">
                <p><strong>Hometown:</strong> <?php echo $hometown; ?></p>
            </div>
        <?php endif; ?>

        <?php if($birthdate): ?>
            <div class="birthdate vital">
                <p><strong>Born:</strong> <?php echo $birthdate->format('F j, Y'); ?> (Age <?php echo $age->y; ?>)</p>
            </div>
        <?php endif; ?>
    </div>

    <?php get_template_part('templates/single-member/career-information'); ?>



</section>