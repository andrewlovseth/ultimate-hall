<?php

    $resources = get_field('resources');
    $headline = $resources['headline'];

    if(have_rows('resources')): while(have_rows('resources')): the_row(); 

?>

    <div class="resources">
        <div class="section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
        </div>

        <ul class="documents">
            <?php if(have_rows('documents')): while(have_rows('documents')): the_row(); ?>

                <?php
                    $document = get_sub_field('document');
                    $title = $document['title'];
                    $url = $document['url'];

                ?>
        
                <li class="document">
                    <a href="<?php echo $url; ?>" rel="external"><?php echo $title; ?></a>
                </li>

            <?php endwhile; endif; ?>
        </ul>
    </div>

<?php endwhile; endif; ?>