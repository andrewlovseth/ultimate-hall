<div class="member">
    <div class="photo">
        <a href="<?php the_permalink(); ?>">
            <div class="content">
                <img src="<?php $image = get_field('photos_headshot'); echo $image['sizes']['thumbnail']; ?>" alt="<?php echo $image['alt']; ?>" />
            </div>
        </a>
    </div>

    <div class="info">
        <div class="name">
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        </div>
    </div>
</div>