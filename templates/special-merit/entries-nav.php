<?php if(have_rows('entries')): ?>
    
    <nav class="entries-nav grid">
        <ul>
            <?php while(have_rows('entries')) : the_row(); ?>

                <?php

                    $vitals = get_sub_field('vitals');
                    $first_name = $vitals['first_name'];
                    $last_name = $vitals['last_name'];

                ?>
                
                <li>
                    <a href="#<?php echo $first_name; ?><?php if($last_name): ?>-<?php echo $last_name; ?><?php endif; ?>" class="smooth">
                        <?php echo $first_name . ' ' . $last_name; ?>
                    </a>
                </li>
                
            <?php endwhile; ?>
        </ul>
    </nav>

<?php endif; ?>