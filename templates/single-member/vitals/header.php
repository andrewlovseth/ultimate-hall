<?php

    $vitals = get_field('vitals');
    $hometown = $vitals['hometown'];
    $birthdate_field = $vitals['birthdate'];
    $birthdate = DateTime::createFromFormat('Ymd', $birthdate_field);

    $date_of_death_field = $vitals['date_of_death'];
    if($date_of_death_field) {
        $date_of_death = DateTime::createFromFormat('Ymd', $date_of_death_field);
    }
    $year_only = $vitals['show_year_only'];

    $photos = get_field('photos');
    $headshot = $photos['headshot'];
   
    $today = new DateTime();
    $birthday = new DateTime($birthdate_field);
    $age = $today->diff($birthday);

    $lifespan = FALSE;

    if($date_of_death_field) {
        $lifespan = $date_of_death->diff($birthday);
    }

?>

<div class="header">
    <?php if($headshot): ?>
        <div class="photo">
            <img src="<?php echo $headshot['url']; ?>" alt="<?php echo $headshot['alt']; ?>" />
        </div>
    <?php endif; ?>

    <div class="name">
        <h2><?php the_title(); ?></h2>
    </div>

    <?php if($hometown): ?>
        <div class="hometown vital">
            <p><strong>Hometown:</strong> <?php echo $hometown; ?></p>
        </div>
    <?php endif; ?>

    <?php if($birthdate): ?>
        <?php if($lifespan): ?>
            <div class="birthdate vital">
                <p>
                    <strong>Born:</strong> <?php if($year_only) { echo $birthdate->format('Y'); } else { echo $birthdate->format('F j, Y'); }; ?><br/>
                    <strong>Died:</strong> <?php if($year_only) { echo $date_of_death->format('Y'); } else { echo $date_of_death->format('F j, Y'); }; ?> (Age <?php echo $lifespan->y; ?>)<br/>
                </p>
            </div>
        <?php else: ?>
            <div class="birthdate vital">
                <p><strong>Born:</strong> <?php if($year_only) { echo $birthdate->format('Y'); } else { echo $birthdate->format('F j, Y'); }; ?> (Age <?php echo $age->y; ?>)</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>