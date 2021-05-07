<?php $classes = get_field('classes', 'options'); if( $classes ): ?>
    <nav class="class-nav grid">
        <ul>
            <?php foreach( $classes as $class ): $year = $class->post_title; ?>

                <li>
                    <a href="#class-<?php echo $year; ?>" class="smooth"><?php echo $year; ?></a>
                </li>
                
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>