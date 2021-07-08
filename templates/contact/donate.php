<?php

    $donate = get_field('donate');
    $headline =  $donate['headline']; 
    $copy =  $donate['copy']; 
    $paypal_embed =  $donate['paypal_embed']; 

?>

<section class="donate grid">
    <div class="info">
        <div class="section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
        </div>

        <div class="copy p3 extended">
            <?php echo $copy; ?>
        </div>

        <?php echo $paypal_embed; ?>
    </div>
</section>