<?php
    $args = wp_parse_args($args);

    if(!empty($args)) {
        $year = $args['year'];
        $team = $args['team']; 
    }
?>
<div class="entry">
    <div class="year">
        <span><?php echo esc_html( $year ); ?></span>
    </div>

    <div class="team">
        <a href="<?php echo esc_url( get_permalink( $team->ID ) ); ?>"><?php echo esc_html( $team->post_title ); ?></a>
    </div>
</div>