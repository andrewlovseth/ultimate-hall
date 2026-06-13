/* ==========================================================================
   Ultimate Hall — Omnibox search (command palette)

   Vanilla JS, no jQuery, no build step. Enqueued in the footer with an
   `uhSearch` config object localized by functions/enqueue-styles-scripts.php:
     uhSearch.restUrl  -> GET {restUrl}?q=<term>&per_group=<int>[&group=<key>]
     uhSearch.homeUrl  -> used for the "View all results" link (?s=...&type=...)

   API contract (functions/search-api.php):
     { query, total, groups: [ { key, label, count, items: [ {id, type, title, url, sub, photo} ] } ] }
   All four groups (members, teams, classes, pages) are always present, in
   order (members, teams, classes, pages), even with count 0. `photo` is a
   thumbnail URL on member items (null/absent when no headshot exists).
   Queries under 2 chars return no groups, so we never fetch below MIN_CHARS.

   Ghost completion: an inline twin element (.omni__ghost) behind the
   transparent-background input paints the typed text invisibly plus a gray
   completion remainder, matched against the currently-loaded result titles
   (members first, then teams, classes, pages). Tab accepts when visible;
   otherwise Tab keeps its role of cycling scopes. ArrowRight at the end of
   the input also accepts. The pool clears while a fetch is in flight so the
   ghost never completes against stale results.

   Security note: every API string is escaped via esc() before being placed
   into innerHTML. URLs (row hrefs, member photo srcs) are assigned through
   element properties (never concatenated into HTML), which avoids attribute
   injection entirely.
   ========================================================================== */
(function () {
    'use strict';

    var cfg = window.uhSearch || {};

    var palette = document.querySelector('.js-omni');
    var openButtons = document.querySelectorAll('.js-omni-open');
    if (!palette || !openButtons.length) return;

    var panel = palette.querySelector('.omni__panel');
    var input = palette.querySelector('.js-omni-input');
    var clearBtn = palette.querySelector('.js-omni-clear');
    var closeEls = palette.querySelectorAll('.js-omni-close');
    var scopesEl = palette.querySelector('.js-omni-scopes');
    var resultsEl = palette.querySelector('.js-omni-results');
    var viewAllEl = palette.querySelector('.js-omni-viewall');
    var ghostTypedEl = palette.querySelector('.js-omni-ghost-typed');
    var ghostRestEl = palette.querySelector('.js-omni-ghost-rest');
    var tabHintEl = palette.querySelector('.js-omni-tabhint');
    if (!panel || !input || !scopesEl || !resultsEl) return;

    var MIN_CHARS = 2;
    var DEBOUNCE_MS = 250;
    var PER_GROUP_ALL = 5;
    var PER_GROUP_SCOPED = 20;
    // v2: payload gained `photo` for member rows. Bumping the key orphans
    // icon-era v1 entries instead of rendering them without leads.
    var RECENTS_KEY = 'uh_omni_recents_v2';
    var RECENTS_CAP = 5;

    /* ---- inline icon set ----
       Lucide 0.544.0 "archival" set, path data inlined (no CDN dependency),
       drawn at stroke-width 1.5 for the engraved, finer line weight. */
    var ICONS = {
        'search': '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
        'x': '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'chevron-right': '<path d="m9 18 6-6-6-6"/>',
        'history': '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'user-round': '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'flag': '<path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 7.333 2q2 0 3.067-.8A1 1 0 0 1 20 4v10a1 1 0 0 1-.4.8A6 6 0 0 1 16 16c-3 0-5-2-8-2a6 6 0 0 0-4 1.528"/>',
        'medal': '<path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/><path d="M12 18v-2h-.5"/>',
        'scroll-text': '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>'
    };

    function icon(name, cls) {
        return '<svg' + (cls ? ' class="' + cls + '"' : '') +
            ' viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"' +
            ' stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"' +
            ' aria-hidden="true" focusable="false">' + (ICONS[name] || '') + '</svg>';
    }

    var SCOPES = [
        { key: 'all',     label: 'All' },
        { key: 'members', label: 'Members', icon: 'user-round' },
        { key: 'teams',   label: 'Teams',   icon: 'flag' },
        { key: 'classes', label: 'Classes', icon: 'medal' },
        { key: 'pages',   label: 'Pages',   icon: 'scroll-text' }
    ];

    /* ---- state ---- */
    var state = {
        scope: 'all',
        rows: [],       // flat list of row <a> elements, for keyboard nav
        active: 0,
        counts: null,   // { all, members, teams, classes, pages } from latest response
        ghostPool: [],  // titles from the currently-loaded results, in group order
        ghostRest: '',  // visible completion remainder ('' = no ghost showing)
        lastFocus: null // element to restore focus to on close
    };
    var cache = new Map();    // "<query>|<scope>" -> parsed response
    var inflight = null;      // AbortController for the in-flight fetch
    var requestSeq = 0;       // discards out-of-order responses (belt + suspenders)
    var debounceTimer = null;

    /* ---- escaping / highlight ---- */
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function highlight(text, q) {
        text = String(text == null ? '' : text);
        if (!q) return esc(text);
        var i = text.toLowerCase().indexOf(q.toLowerCase());
        if (i === -1) return esc(text);
        return esc(text.slice(0, i)) +
            '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' +
            esc(text.slice(i + q.length));
    }

    /* ---- recents (localStorage, best-effort: private mode may throw) ---- */
    function getRecents() {
        try {
            var parsed = JSON.parse(localStorage.getItem(RECENTS_KEY) || '[]');
            if (!Array.isArray(parsed)) return [];
            return parsed.filter(function (r) {
                return r && typeof r === 'object' && typeof r.url === 'string';
            }).slice(0, RECENTS_CAP);
        } catch (e) {
            return [];
        }
    }

    function pushRecent(item) {
        if (!item || !item.url) return;
        try {
            var next = getRecents().filter(function (r) {
                return r && r.url !== item.url;
            });
            next.unshift({
                type: item.type || '',
                title: item.title || '',
                url: item.url,
                sub: item.sub || '',
                photo: typeof item.photo === 'string' ? item.photo : ''
            });
            localStorage.setItem(RECENTS_KEY, JSON.stringify(next.slice(0, RECENTS_CAP)));
        } catch (e) {
            /* storage unavailable — recents are a nicety, not a requirement */
        }
    }

    /* ---- row leads (locked "Photo rows" treatment) ----
       Members: 40px circular headshot, initials disc when no photo.
       Teams: initials disc on a light blue tint. Classes: medal disc on gold.
       Pages: scroll-text disc on gray. Recents reuse the same logic. */
    function initials(title) {
        var words = String(title == null ? '' : title).split(/\s+/);
        var out = '';
        for (var i = 0; i < words.length && out.length < 2; i++) {
            // Skip leading quotes/punctuation so 'Alex "Dutchy" G.' gives AD.
            var ch = words[i].replace(/^[^0-9A-Za-z]+/, '').charAt(0);
            if (ch) out += ch.toUpperCase();
        }
        return out || '?';
    }

    function makeLead(item) {
        var lead = document.createElement('span');
        var type = item.type || '';

        if (type === 'member' || type === 'members') {
            lead.className = 'omni-result__lead';
            if (item.photo && typeof item.photo === 'string') {
                var img = document.createElement('img');
                img.src = item.photo; // property assignment — never templated HTML
                img.alt = '';
                img.loading = 'lazy';
                lead.appendChild(img);
            } else {
                lead.textContent = initials(item.title);
            }
        } else if (type === 'team' || type === 'teams') {
            lead.className = 'omni-result__lead omni-result__lead--team';
            lead.textContent = initials(item.title);
        } else if (type === 'class' || type === 'classes' || type === 'year') {
            lead.className = 'omni-result__lead omni-result__lead--class';
            lead.innerHTML = icon('medal');
        } else {
            lead.className = 'omni-result__lead omni-result__lead--page';
            lead.innerHTML = icon('scroll-text');
        }
        return lead;
    }

    /* ---- rendering ---- */
    function groupHead(label, count, iconName) {
        return '<header class="omni-group__head">' +
            '<span class="omni-group__title">' + (iconName ? icon(iconName) : '') + esc(label) + '</span>' +
            (count == null ? '' : '<span class="omni-group__count">' + esc(count) + '</span>') +
            '</header>';
    }

    function makeRow(item, q) {
        var row = document.createElement('a');
        row.className = 'omni-result';
        row.href = item.url || '#';

        row.appendChild(makeLead(item));

        var body = document.createElement('span');
        body.className = 'omni-result__body';
        body.innerHTML =
            '<span class="omni-result__title">' + highlight(item.title, q) + '</span>' +
            (item.sub ? '<span class="omni-result__sub">' + esc(item.sub) + '</span>' : '');
        row.appendChild(body);

        // icon() output is a static literal (no API data); parse it detached
        // and append the resulting <svg> node directly.
        var chevWrap = document.createElement('span');
        chevWrap.innerHTML = icon('chevron-right', 'omni-result__chevron');
        if (chevWrap.firstChild) row.appendChild(chevWrap.firstChild);

        row._omniItem = item;

        row.addEventListener('mousemove', function () {
            var i = state.rows.indexOf(row);
            if (i !== -1 && i !== state.active) setActive(i);
        });

        row.addEventListener('click', function (e) {
            pushRecent(item);
            // Plain left click navigates via the anchor itself; modified clicks
            // (cmd/ctrl/shift/middle) open elsewhere, so keep the palette open.
            if (!e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey) close();
        });

        state.rows.push(row);
        return row;
    }

    function setActive(idx) {
        state.active = idx;
        state.rows.forEach(function (row, i) {
            row.classList.toggle('is-active', i === idx);
        });
        if (state.rows[idx]) state.rows[idx].scrollIntoView({ block: 'nearest' });
    }

    function renderGroups(data, q) {
        resultsEl.innerHTML = '';
        resultsEl.scrollTop = 0;
        state.rows = [];

        var groups = (data && data.groups) || [];
        var visible = groups.filter(function (g) {
            if (state.scope !== 'all' && g.key !== state.scope) return false;
            return g.items && g.items.length > 0;
        });

        if (!visible.length) {
            resultsEl.innerHTML = '<p class="omni__empty">No matches for &ldquo;' + esc(q) +
                '&rdquo;. Try a different keyword or scope.</p>';
            return;
        }

        var scopeIcons = {};
        SCOPES.forEach(function (s) { scopeIcons[s.key] = s.icon; });

        visible.forEach(function (g) {
            var section = document.createElement('section');
            section.className = 'omni-group';
            section.innerHTML = groupHead(g.label, g.count, scopeIcons[g.key]);
            g.items.forEach(function (item) {
                section.appendChild(makeRow(item, q));
            });
            resultsEl.appendChild(section);
        });

        setActive(0);
    }

    function renderEmptyState() {
        resultsEl.innerHTML = '';
        resultsEl.scrollTop = 0;
        state.rows = [];

        var wrap = document.createElement('div');
        wrap.className = 'omni-empty';

        var recents = getRecents();
        if (recents.length) {
            var section = document.createElement('section');
            section.className = 'omni-group';
            section.innerHTML = groupHead('Recent', null, 'history');
            recents.forEach(function (item) {
                section.appendChild(makeRow(item, ''));
            });
            wrap.appendChild(section);
        }

        var hero = document.createElement('div');
        hero.className = 'omni-empty__hero';
        hero.innerHTML =
            '<p class="omni-empty__title">Search the Hall of Fame</p>' +
            '<p class="omni-empty__note">Find inductees, teams, induction classes, news, and pages. ' +
            'Start typing &mdash; completions appear as ghost text, <kbd>Tab</kbd> accepts them.</p>';
        wrap.appendChild(hero);

        resultsEl.appendChild(wrap);
        if (state.rows.length) setActive(0);
    }

    function renderError() {
        state.rows = [];
        resultsEl.innerHTML = '<p class="omni__empty">Search is having trouble right now. ' +
            'Check your connection and try again in a moment.</p>';
    }

    function renderScopes() {
        scopesEl.innerHTML = '';
        SCOPES.forEach(function (s) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'omni-scope' + (s.key === state.scope ? ' is-active' : '');
            btn.setAttribute('aria-pressed', s.key === state.scope ? 'true' : 'false');
            var count = state.counts ? state.counts[s.key] : null;
            btn.innerHTML = esc(s.label) +
                (count == null ? '' : ' <span class="omni-scope__count">' + esc(count) + '</span>');
            btn.addEventListener('click', function () {
                setScope(s.key);
            });
            scopesEl.appendChild(btn);
        });
    }

    /* Counts come back on every response (all four groups are always present),
       so the pills stay consistent whether the last fetch was "all" or scoped. */
    function updateCounts(data) {
        var counts = { all: 0 };
        ((data && data.groups) || []).forEach(function (g) {
            counts[g.key] = g.count || 0;
            counts.all += g.count || 0;
        });
        state.counts = counts;
    }

    /* ---- ghost completion ----
       Candidates are the titles currently on screen, members first (the API
       always returns groups in members, teams, classes, pages order). No ghost
       when: query empty, exact match (no remainder), the match isn't a prefix,
       or a fetch is in flight (the pool is cleared until the response lands).
       Matching runs on the raw (untrimmed) input so the invisible twin always
       mirrors the real text glyph-for-glyph. */
    function ghostPoolFrom(data) {
        var pool = [];
        ((data && data.groups) || []).forEach(function (g) {
            (g.items || []).forEach(function (item) {
                if (item && item.title) pool.push(String(item.title));
            });
        });
        return pool;
    }

    function updateGhost() {
        var raw = input.value;
        var rest = '';
        if (raw.length && state.ghostPool.length) {
            var low = raw.toLowerCase();
            for (var i = 0; i < state.ghostPool.length; i++) {
                var cand = state.ghostPool[i];
                var candLow = cand.toLowerCase();
                if (candLow.indexOf(low) === 0 && candLow.length > low.length) {
                    rest = cand.slice(raw.length);
                    break;
                }
            }
        }
        state.ghostRest = rest;
        if (ghostTypedEl) ghostTypedEl.textContent = rest ? raw : '';
        if (ghostRestEl) ghostRestEl.textContent = rest;
        if (tabHintEl) tabHintEl.textContent = rest ? 'Complete' : 'Scope';
    }

    function clearGhostPool() {
        state.ghostPool = [];
        updateGhost();
    }

    function acceptGhost() {
        if (!state.ghostRest) return false;
        input.value = input.value + state.ghostRest;
        var end = input.value.length;
        input.setSelectionRange(end, end);
        refresh({ immediate: true });
        return true;
    }

    /* ---- view all (search results page) ---- */
    function viewAllUrl(q) {
        var url = (cfg.homeUrl || '/') + '?s=' + encodeURIComponent(q);
        if (state.scope !== 'all') url += '&type=' + encodeURIComponent(state.scope);
        return url;
    }

    function updateViewAll(q) {
        if (!viewAllEl) return;
        if (q.length >= MIN_CHARS) {
            viewAllEl.href = viewAllUrl(q);
            viewAllEl.hidden = false;
        } else {
            viewAllEl.hidden = true;
        }
    }

    /* ---- fetching ---- */
    function buildUrl(q, scope) {
        var url = new URL(cfg.restUrl);
        url.searchParams.set('q', q);
        if (scope === 'all') {
            url.searchParams.set('per_group', String(PER_GROUP_ALL));
        } else {
            url.searchParams.set('group', scope);
            url.searchParams.set('per_group', String(PER_GROUP_SCOPED));
        }
        return url.toString();
    }

    function applyResponse(data, q) {
        updateCounts(data);
        renderScopes();
        renderGroups(data, q);
        updateViewAll(q);
        state.ghostPool = ghostPoolFrom(data);
        updateGhost();
    }

    function search(q, scope) {
        var key = q + '|' + scope;

        if (cache.has(key)) {
            applyResponse(cache.get(key), q);
            return;
        }

        if (!cfg.restUrl || typeof window.fetch !== 'function') {
            renderError();
            return;
        }

        var requestUrl;
        try {
            requestUrl = buildUrl(q, scope); // new URL() throws on a malformed restUrl
        } catch (e) {
            renderError();
            return;
        }

        if (inflight) inflight.abort();
        inflight = new AbortController();
        var seq = ++requestSeq;

        // Fetch in flight: never complete against the now-stale results.
        clearGhostPool();

        fetch(requestUrl, {
            signal: inflight.signal,
            headers: { Accept: 'application/json' }
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Search request failed: ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (seq !== requestSeq) return; // superseded by a newer request
                cache.set(key, data);
                if (palette.hidden) return;
                // Only paint if the UI still wants this exact query + scope.
                if (q !== input.value.trim() || scope !== state.scope) return;
                applyResponse(data, q);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                if (seq !== requestSeq) return;
                renderError();
            });
    }

    /* ---- refresh: single entry point after any input/scope change ---- */
    function refresh(opts) {
        var immediate = opts && opts.immediate;
        var q = input.value.trim();

        if (clearBtn) clearBtn.hidden = input.value.length === 0;
        clearTimeout(debounceTimer);

        if (q.length < MIN_CHARS) {
            if (inflight) inflight.abort();
            state.counts = null;
            clearGhostPool();
            renderScopes();
            renderEmptyState();
            updateViewAll(q);
            return;
        }

        renderScopes();
        updateViewAll(q);
        // Re-match the (possibly longer) raw text against the currently-loaded
        // results so the ghost tracks every keystroke during the debounce gap.
        updateGhost();

        if (immediate) {
            search(q, state.scope);
        } else {
            debounceTimer = setTimeout(function () {
                search(q, state.scope);
            }, DEBOUNCE_MS);
        }
    }

    function setScope(key) {
        state.scope = key;
        refresh({ immediate: true });
        input.focus();
    }

    /* ---- open / close ---- */
    function open() {
        if (!palette.hidden) return;
        state.lastFocus = document.activeElement;
        // Never stack on top of the mobile nav overlay (site.js owns that class).
        document.body.classList.remove('nav-overlay-open');
        document.body.classList.add('omni-open');
        palette.hidden = false;
        input.value = '';
        state.scope = 'all';
        refresh({ immediate: true });
        window.requestAnimationFrame(function () {
            input.focus();
        });
    }

    function close() {
        if (palette.hidden) return;
        palette.hidden = true;
        document.body.classList.remove('omni-open');
        clearTimeout(debounceTimer);
        if (inflight) inflight.abort();
        if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
            state.lastFocus.focus();
        }
        state.lastFocus = null;
    }

    /* ---- events ---- */
    Array.prototype.forEach.call(openButtons, function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            open();
        });
    });

    Array.prototype.forEach.call(closeEls, function (el) {
        el.addEventListener('click', close);
    });

    input.addEventListener('input', function () {
        refresh();
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            refresh({ immediate: true });
            input.focus();
        });
    }

    /* Keyboard, scoped to the panel. Tab conflict resolution: when ghost text
       is visible, Tab accepts the completion; otherwise Tab keeps its shipped
       role of cycling scopes (Shift+Tab always cycles). ArrowRight with the
       caret at the end of the input also accepts. Arrows move the active row,
       Enter selects. */
    panel.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            if (!e.shiftKey && state.ghostRest && acceptGhost()) return;
            var i = SCOPES.findIndex(function (s) { return s.key === state.scope; });
            var next = (i + (e.shiftKey ? SCOPES.length - 1 : 1)) % SCOPES.length;
            setScope(SCOPES[next].key);
            return;
        }

        if (e.key === 'ArrowRight' && e.target === input && state.ghostRest &&
            input.selectionStart === input.value.length &&
            input.selectionEnd === input.value.length) {
            e.preventDefault();
            acceptGhost();
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (state.rows.length) setActive(Math.min(state.active + 1, state.rows.length - 1));
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (state.rows.length) setActive(Math.max(state.active - 1, 0));
            return;
        }

        if (e.key === 'Enter' && e.target === input) {
            e.preventDefault();

            if (e.metaKey || e.ctrlKey) {
                var q = input.value.trim();
                if (q.length >= MIN_CHARS) window.location.assign(viewAllUrl(q));
                return;
            }

            var row = state.rows[state.active];
            if (row && row.href) {
                pushRecent(row._omniItem);
                window.location.assign(row.href);
            }
        }
    });

    /* Global shortcuts. "/" and Cmd/Ctrl+K open (never while typing elsewhere);
       Esc closes. While the palette is open we return early so "/" can be
       typed into the search input, and nothing leaks to other handlers. */
    document.addEventListener('keydown', function (e) {
        if (!palette.hidden) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
            return;
        }

        var t = e.target;
        var typing = t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable);
        if (typing) return;

        if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey) {
            e.preventDefault();
            open();
        } else if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            open();
        }
    });
})();
