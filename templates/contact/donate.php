<?php

    $donate = get_field('donate');
    $headline =  $donate['headline']; 
    $copy =  $donate['copy']; 
    $paypal_embed =  $donate['paypal_embed']; 
    $venmo_embed =  $donate['venmo_embed']; 

?>

<section class="donate grid">
    <div class="info">
        <div class="section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
        </div>

        <div class="copy p3 extended">
            <?php echo $copy; ?>
        </div>

        <div class="donate__options">
            <?php if($paypal_embed): ?>
                <div class="donate__option paypal">
                    <h4>PayPal</h4>
                    <?php echo $paypal_embed; ?>
                </div>
            <?php endif; ?>

            <?php if($venmo_embed): ?>
                <div class="donate__option venmo">
                    <h4>Venmo</h4>
                    <?php echo $venmo_embed; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>