(function ($, window, document, undefined) {
    $(document).ready(function ($) {
        $('.smooth, .copy a').smoothScroll();

        // rel="external"
        $('a[rel="external"]').click(function () {
            window.open($(this).attr('href'));
            return false;
        });

        // Nav Trigger
        $('.js-nav-trigger').click(function () {
            $('body').toggleClass('nav-overlay-open');
            return false;
        });

        $('.gallery-slider').slick({
            dots: true,
            infinite: true,
            speed: 300,
            slidesToShow: 1,
            centerMode: true,
            variableWidth: true,
        });

        $('.slick-slider').on('click', '.slick-slide', function (e) {
            e.stopPropagation();
            var index = $(this).data('slick-index');
            if ($('.slick-slider').slick('slickCurrentSlide') !== index) {
                $('.slick-slider').slick('slickGoTo', index);
            } else {
            }
        });

        $('.hero-slider').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 300,
            slidesToShow: 1,
            fade: true,
            cssEase: 'linear',
            autoplay: true,
            autoplaySpeed: 5000,
        });
    });

    $(document).mouseup(function (e) {
        var menu = $('.site-navigation, .js-nav-trigger');

        if (!menu.is(e.target) && menu.has(e.target).length === 0) {
            $('body').removeClass('nav-overlay-open');
        }
    });
})(jQuery, window, document);
