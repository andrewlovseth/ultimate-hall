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
        <div class="headline section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
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