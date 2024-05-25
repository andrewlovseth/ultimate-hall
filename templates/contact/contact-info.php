<?php

    $contact = get_field('contact');
    $headline =  $contact['headline']; 
    $copy =  $contact['copy']; 
    $email =  $contact['email']; 
    $facebook =  $contact['facebook']; 
    $twitter =  $contact['twitter']; 
    $instagram =  $contact['instagram']; 
    $youtube =  $contact['youtube']; 

?>

<section class="contact-info grid">
    <div class="info">

<form action="https://www.paypal.com/donate" method="post" target="_top">
    <input type="hidden" name="hosted_button_id" value="YDQJR76F7T4ES" />
Donate    <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>


    <div class="headline">
            <h2 class="page-title"><?php echo $headline; ?></h2>
        </div>

        <div class="copy p1">
            <?php echo $copy; ?>
        </div>
    </div>

    <div class="links">
        <div class="email">
            <h4>Email</h4>
            <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>
        </div>

        <div class="social">
            <h4>Social</h4>

            <div class="link facebook">
                <a href="<?php echo $facebook; ?>" rel="external">
                    <img src="<?php bloginfo('template_directory'); ?>/images/icon-facebook-blue.svg" alt="Facebook" />
                </a>
            </div>

            <div class="link twitter">
                <a href="<?php echo $twitter; ?>" rel="external">
                    <img src="<?php bloginfo('template_directory'); ?>/images/icon-twitter-blue.svg" alt="Twitter" />
                </a>
            </div>

            <div class="link instagram">
                <a href="<?php echo $instagram; ?>" rel="external">
                    <img src="<?php bloginfo('template_directory'); ?>/images/icon-instagram-blue.svg" alt="Instagram" />
                </a>
            </div>

            <div class="link youtube">
                <a href="<?php echo $youtube; ?>" rel="external">
                    <img src="<?php bloginfo('template_directory'); ?>/images/icon-youtube-blue.svg" alt="YouTube" />
                </a>
            </div>
        </div>    
    </div>
</section>