<?php

    $vetting_committee = get_field('vetting_committee');
    $headline = $vetting_committee['headline'];

    if(have_rows('vetting_committee')): while(have_rows('vetting_committee')): the_row();

?>

    <section class="vetting-committee">
        <div class="section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
        </div>

        <div class="members">
            <?php if(have_rows('members')): while(have_rows('members')): the_row(); ?>

                <?php
                    $title = get_sub_field('title');
                    $member = get_sub_field('member');
                    $photo = get_field('photos_headshot', $member->ID);
                    $class = get_field('meta_class', $member->ID);

                ?>
        
                <div class="member">
                    <a href="<?php echo get_permalink($member->ID); ?>">
                        <div class="photo">
                            <div class="content">
                                <?php echo wp_get_attachment_image($photo['ID'], 'medium'); ?>
                            </div>
                        </div>

                        <div class="info">
                            <div class="name">
                                <h4>
                                    <?php echo get_the_title($member); ?>
                                    <span class="class">Class of <?php echo $class->post_title; ?></span>
                                </h4>
                            </div>
                            <div class="title">
                                <h5><?php echo $title; ?></h5>
                            </div>
                        </div>
                    </a>
                </div>

            <?php endwhile; endif; ?>
        </div>
    </section>

<?php endwhile; endif; ?>