<?php

require_once( plugin_dir_path( __FILE__ ) . '/functions/theme-support.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/enqueue-styles-scripts.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/acf.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/register-blocks.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/disable-gutenberg-editor.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/divisions.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/query-helpers.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/member-helpers.php');

require_once( plugin_dir_path( __FILE__ ) . '/functions/inaugural-helpers.php');

// Fix blog pagination: /news/page/2/ gets swallowed by post name rewrite rules
// because the permalink structure starts with /news/. This adds an explicit rule.
add_action('init', function () {
    add_rewrite_rule(
        'news/page/?([0-9]{1,})/?$',
        'index.php?pagename=news&paged=$matches[1]',
        'top'
    );
});