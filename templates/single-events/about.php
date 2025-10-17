<?php

    $copy = get_field('description');
    $video_id = get_field('video_id');
    $location = get_field('location');
    $date = get_the_time('F j, Y');
    $classes = get_field('classes');
    $program = get_field('program');

?>

<section class="about grid">

    <?php if($video_id): ?>
        <div class="video">
            <iframe src="https://www.youtube.com/embed/<?php echo $video_id; ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    <?php endif; ?>


    <div class="copy p1 extended">
        <?php echo $copy; ?>
    </div>

    <div class="sidebar">

        <div class="sidebar__location">
            <div class="section-header">
                <h3 class="x-small">Location</h3>
            </div>
            
            <div class="copy p3">
                <?php echo $location; ?>
            </div>
        </div>      

        <div class="sidebar__date">
            <div class="section-header">
                <h3 class="x-small">Date</h3>
            </div>
            
            <div class="copy p3">
                <?php echo $date; ?>
            </div>
        </div>

        <div class="sidebar__classes">
            <div class="section-header">
                <h3 class="x-small">Inducted Classes</h3>
            </div>

            <?php 
                
                if ($classes && is_array($classes)):
                    usort($classes, function($a, $b) {
                        $yearA = intval(get_post_field('post_title', $a->ID));
                        $yearB = intval(get_post_field('post_title', $b->ID));
                        if ($yearA === $yearB) { return 0; }
                        return ($yearA < $yearB) ? -1 : 1; // earliest to latest
                    });
            ?>
                <ul class="classes-list">
                    <?php foreach ($classes as $year_post): 
                        $year_title = get_post_field('post_title', $year_post->ID);
                        $year_link  = get_permalink($year_post->ID);
                    ?>
                        <li class="classes-item">
                            <a href="<?php echo esc_url($year_link); ?>"><?php echo esc_html($year_title); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if($program): ?>
            <div class="sidebar__program">
                <div class="section-header">
                    <h3 class="x-small">Program</h3>
                </div>
                
                <div class="copy p3">
                    <a href="<?php echo $program['url']; ?>" target="_blank"><?php echo esc_html($program['title']); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </section>