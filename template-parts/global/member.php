<?php
    $args = wp_parse_args($args);

    if(!empty($args)) {
        $member_ID = $args['member_ID'];
    } else {
        $member_ID = $post->ID;
    }

    $type = get_field('meta_induction_type', $member_ID);
    $division = get_field('meta_induction_division', $member_ID);

    if($type['value'] == 'special-merit') {
        $image = get_field('introduction_photo', $member_ID); 
    } else {
        $image = get_field('photos_headshot', $member_ID); 
    }

?>

<div class="member">
    <div class="photo">
        <a href="<?php the_permalink(); ?>">
            <div class="content">
                <?php if($image): ?>
                    <img src="<?php echo $image['sizes']['thumbnail']; ?>" alt="<?php echo $image['alt']; ?>" />
                <?php else: ?>
                    <div class="empty"></div>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="info">
        <div class="name">
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        </div>

        <?php if($type || $division): ?>
            <div class="meta">
                <h4>
                    <?php if($type): ?>
                        <span class="type"><?php echo $type['label']; ?></span>                        
                    <?php endif; ?>

                    <?php if($division): ?>
                        <span class="division"><?php echo $division['label']; ?></span>
                    <?php endif; ?>                
                </h4>
            </div>
        <?php endif; ?>
    </div>
</div>