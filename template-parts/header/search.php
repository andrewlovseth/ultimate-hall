<?php
/*
    Omnibox Search — header trigger + command palette shell.

    The trigger is included inside the header tray cell (.header-tray__search)
    and fills its width — placement/sizing of the cell belongs to the tray.
    The palette (.omni) is position: fixed, so it can live here without
    affecting header layout. All behavior lives in js/search.js, which renders
    scope pills and results into the .js-omni-* hooks below. Result rows use
    the same .omni-result markup contract as the server-rendered search
    results page (search.php).

    Icons are inline Lucide 0.544.0 ("archival" set, stroke-width 1.5) — no
    CDN dependency. The .omni__ghost twin sits behind the transparent input
    and paints the inline ghost completion (see js/search.js).
*/
?>

<button type="button" class="omni-trigger js-omni-open" aria-haspopup="dialog">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
    <span class="omni-trigger__label">Search</span>
    <kbd class="omni-trigger__kbd" aria-hidden="true">/</kbd>
</button>

<div class="omni js-omni" hidden>
    <div class="omni__scrim js-omni-close"></div>

    <div class="omni__panel" role="dialog" aria-modal="true" aria-label="Search">
        <div class="omni__searchrow">
            <svg class="omni__searchicon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>

            <div class="omni__inputwrap">
                <div class="omni__ghost" aria-hidden="true"><span class="omni__ghost-typed js-omni-ghost-typed"></span><span class="omni__ghost-rest js-omni-ghost-rest"></span></div>
                <input type="text" class="omni__input js-omni-input" placeholder="Search the Hall of Fame&hellip;" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" aria-label="Search the Hall of Fame">
            </div>

            <button type="button" class="omni__clear js-omni-clear" aria-label="Clear search" hidden>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>

            <button type="button" class="omni__close js-omni-close" aria-label="Close search">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="omni__scopes js-omni-scopes"></div>

        <div class="omni__results js-omni-results"></div>

        <div class="omni__footer">
            <a class="omni__viewall js-omni-viewall" href="<?php echo esc_url( home_url( '/' ) ); ?>" hidden>View all results <kbd>Ctrl</kbd><kbd>&crarr;</kbd></a>

            <div class="omni__hints">
                <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> Navigate</span>
                <span><kbd>&crarr;</kbd> Select</span>
                <span><kbd>Tab</kbd> <span class="js-omni-tabhint">Scope</span></span>
                <span><kbd>Esc</kbd> Close</span>
            </div>
        </div>
    </div>
</div>
