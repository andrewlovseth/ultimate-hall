<?php

require_once get_theme_file_path( 'functions/theme-support.php' );

require_once get_theme_file_path( 'functions/enqueue-styles-scripts.php' );

require_once get_theme_file_path( 'functions/acf.php' );

require_once get_theme_file_path( 'functions/register-blocks.php' );

require_once get_theme_file_path( 'functions/disable-gutenberg-editor.php' );

require_once get_theme_file_path( 'functions/query-helpers.php' );

require_once get_theme_file_path( 'functions/member-helpers.php' );

require_once get_theme_file_path( 'functions/inaugural-helpers.php' );

require_once get_theme_file_path( 'functions/search-api.php' );

require_once get_theme_file_path( 'functions/model.php' );

require_once get_theme_file_path( 'functions/cli.php' );

require_once get_theme_file_path( 'functions/rest.php' );

require_once get_theme_file_path( 'functions/abilities.php' );

require_once get_theme_file_path( 'functions/mcp.php' );

require_once get_theme_file_path( 'functions/oauth.php' );

require_once get_theme_file_path( 'functions/admin-columns.php' );

require_once get_theme_file_path( 'functions/svg.php' );

// Fix blog pagination: /news/page/2/ gets swallowed by post name rewrite rules
// because the permalink structure starts with /news/. This adds an explicit rule.
add_action('init', function () {
    add_rewrite_rule(
        'news/page/?([0-9]{1,})/?$',
        'index.php?pagename=news&paged=$matches[1]',
        'top'
    );
});