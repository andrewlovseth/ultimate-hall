<?php

    $vitals = get_sub_field('vitals');
    $photo = $vitals['photo'];
    $first_name = $vitals['first_name'];
    $last_name = $vitals['last_name'];
    $hometown = $vitals['hometown'];
    $birthdate_field = $vitals['birthdate'];
    $birthdate = DateTime::createFromFormat('Ymd', $birthdate_field);

    $date_of_death_field = $vitals['date_of_death'];
    if($date_of_death_field) {
        $date_of_death = DateTime::createFromFormat('Ymd', $date_of_death_field);
    }

    $today = new DateTime();
    $birthday = new DateTime($birthdate_field);
    $age = $today->diff($birthday);

    $lifespan = FALSE;

    if($date_of_death_field) {
        $lifespan = $date_of_death->diff($birthday);
    }

    $info = get_sub_field('info');
    $bio = $info['bio'];


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
                        <strong>Born:</strong> <?php echo $birthdate->format('F j, Y'); ?><br/>
                        <strong>Died:</strong> <?php echo $date_of_death->format('F j, Y'); ?> (Age <?php echo $lifespan->y; ?>)<br/>
                    </p>
                </div>
            <?php else: ?>
                <div class="birthdate vital">
                    <p><strong>Born:</strong> <?php echo $birthdate->format('F j, Y'); ?> (Age <?php echo $age->y; ?>)</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if($bio): ?>
        <div class="copy bio">
            <?php echo $bio; ?>
        </div>
    <?php endif; ?>
</div>