<div class="member">
    <div class="photo">
        <a href="<?php the_permalink(); ?>">
            <div class="content">
                <?php $image = get_field('photos_headshot'); if($image): ?>
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
    </div>
</div>