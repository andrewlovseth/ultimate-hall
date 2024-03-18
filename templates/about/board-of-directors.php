<?php

$board_of_directors = get_field('board_of_directors');
$headline = $board_of_directors['headline'];
$copy = $board_of_directors['copy'];

if(have_rows('board_of_directors')): while(have_rows('board_of_directors')): the_row();

?>

    <section id="board-of-directors" class="board-of-directors grid">
        <div class="section-header align-center">
            <h3><?php echo $headline; ?></h3>

            <div class="copy p2 extended">
                <?php echo $copy; ?>
            </div>
        </div>

        <div class="members member-grid">
            <?php if(have_rows('members')): while(have_rows('members')): the_row(); ?>

                <?php
                    $title = get_sub_field('title');
                    $member = get_sub_field('member');

                    if(get_field('board_of_directors_headshot', $member->ID)) {
                        $photo = get_field('board_of_directors_headshot', $member->ID);
                    } else {
                        $photo = get_field('photos_headshot', $member->ID);
                    }

                    if(get_field('board_of_directors_name', $member->ID)) {
                        $name = get_field('board_of_directors_name', $member->ID);
                    } else {
                        $name = get_the_title($member);
                    }

                    $class = get_field('meta_class', $member->ID);

                ?>
        
                <div class="member">
                    <a href="<?php echo get_permalink($member->ID); ?>">
                        <div class="photo">
                            <div class="content">
                                <?php if($photo): ?>
                                    <?php echo wp_get_attachment_image($photo['ID'], 'medium'); ?>
                                <?php else: ?>
                                    <div class="empty"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="info">
                            <div class="name">
                                <h4>
                                    <?php echo $name; ?>
                                    <span class="class">Class of <?php echo $class->post_title; ?></span>
                                </h4>
                            </div>

                            <?php if($title): ?>
                                <div class="title">
                                    <h5><?php echo $title; ?></h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>

            <?php endwhile; endif; ?>
        </div>
    </section>

<?php endwhile; endif; ?>