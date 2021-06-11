<?php

    $partners = get_field('partners');
    $headline = $partners['headline'];
    $copy = $partners['copy'];
    $logos = $partners['gallery'];

?>

<section class="partners grid">
    <div class="section-header">
        <h3><?php echo $headline; ?></h3>
    </div>

    <div class="copy p1 extended">
        <?php echo $copy; ?>
    </div>

    <?php  if( $logos ): ?>
        <div class="logos">
            <?php foreach( $logos as $logo ): ?>
                <div class="logo">
                    <img src="<?php echo $logo['url']; ?>" alt="<?php echo $logo['alt']; ?>" />
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>;
</section>