<?php

    $vitals = get_sub_field('vitals');
    $photo = $vitals['photo'];
    $first_name = $vitals['first_name'];
    $last_name = $vitals['last_name'];
    $hometown = $vitals['hometown'];
    $birthdate_field = $vitals['birthdate'];
    $birthdate = FALSE;
    if($birthdate_field) {
        $birthdate = DateTime::createFromFormat('Ymd', $birthdate_field);
    }

    $date_of_death_field = $vitals['date_of_death'];
    $date_of_death = FALSE;
    if($date_of_death_field) {
        $date_of_death = DateTime::createFromFormat('Ymd', $date_of_death_field);
    }

    $age = FALSE;
    if($birthdate) {
        $today = new DateTime();
        $age = $today->diff($birthdate);
    }

    $lifespan = FALSE;
    if($birthdate && $date_of_death) {
        $lifespan = $date_of_death->diff($birthdate);
    }




?>

<div class="vitals">
    <div class="header">
            <div class="photo">
                <?php if($photo): ?>
                    <?php echo wp_get_attachment_image($photo, 'large'); ?>
                <?php else: ?>
                    <img src="<?php bloginfo('template_directory'); ?>/images/FPO-member.jpg" alt="FPO" />                
                <?php endif; ?>
            </div>

        <div class="name">
            <h2><?php echo $first_name . ' ' . $last_name; ?></h2>
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
                        <strong>Born:</strong> <?php $birthdate->format('Y'); ?><br/>
                        <strong>Died:</strong> <?php $date_of_death->format('Y'); ?> (Age <?php echo $lifespan->y; ?>)<br/>
                    </p>
                </div>
            <?php else: ?>
                <div class="birthdate vital">
                    <p><strong>Born:</strong> <?php $birthdate->format('Y'); ?> (Age <?php echo $age->y; ?>)</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>