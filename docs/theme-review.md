# Theme review, 2026-11-21

Read-only review of the whole theme. Fixes land as a PR train: each PR is based on the one before it, so merge in order.

Checked and clean: no private member fields in the public REST API; search API SQL prepared and inputs sanitized; search.php and js/search.js escape output; CLI SQL prepared.

| # | Batch | Status |
| --- | --- | --- |
| 1 | Teammates query explosion (~850 queries/page) and per-request results cache | PR #1 |
| 2 | Asset hardening: CDN jQuery 3.4.1, unsanitized SVG upload filter, plugins.js cache version, noopener | PR #2 |
| 3 | Escaping in member templates; sanitize ability text input | PR #3 |
| 4 | Render bugs and PHP 8 warnings | PR #4 |
| 5 | Dead and duplicated code | PR #5 |
| 6 | Ability permission tightening | PR #6 |

## 3. Escaping and ability input
- Writable via abilities, printed raw: biography.php:33 service description; vitals/header.php:41 hometown; teammates.php:45 first name (fixed in #1).
- abilities.php: sanitize_text_field on description, note, vitals; cast years/flags in update-row; end_year < start_year check in update-row.
- Editor-only content, defense in depth: biography.php:20 wp_kses_post, :47 esc_url; interview.php:14,17; letters-of-recommendation.php:15; vitals/header.php:31; template-parts/global/member.php:27,44,48; profile-header.php:11,20,24; vitals/career-entry.php:11,15; vitals/career-information.php:67-68,88-89; vitals/awards.php:11-12; special-merit/vitals.php:47,52.
- Page templates (contact, events, about, home) same pattern; donate.php embed intentionally raw.

## 4. Render bugs and PHP 8 warnings
- special-merit/vitals.php:60,61,66: missing echo, birth year never shows.
- single-team/other-teams.php:3-9: undefined $division for teams with no division (babymaker, two beach Team USA).
- Null guards: profile-header.php:2-6, vitals.php:3, vitals/header.php:3-17, blocks/hall-class/hall-class.php:25-27; no DateTime on empty dates.
- profile-header.php:6: class label from year slug; use post_title.
- home/news.php:35-38: byte substr on excerpt; use wp_trim_words.
- search-api.php:95 and model.php both build the search index on acf/save_post; drop the search-api hook.

## 5. Dead and duplicated code
- query-helpers.php:8-32 bearsmith_modify_repeater_meta_query (no callers; banned LIKE pattern).
- functions/divisions.php (never called; duplicates uh_team_group).
- Unreferenced: templates/special-merit/entries-nav.php, templates/special-merit/profile-header.php, template-parts/svg/icon-close.php.
- single-team/national-team-members.php identical to members.php.
- disable-gutenberg-editor.php:44 old Gutenberg-plugin filter; theme-support.php wp_body_open polyfill; update_option calls on every request.
- functions.php requires: plugin_dir_path with double slash; use get_theme_file_path.
- Keep cli.php migrate/cleanup-legacy until cleanup-legacy has run on production.

## 6. Ability permissions
- uh_can_read is edit_posts: Contributors could read any member incl. drafts and birthdates, and editors' logins via history. Require edit_others_posts.
- Top-level meta.public also exposes the tools on mcp-adapter's default server; restrict to the uhof server.
- uh_raw_rows / update-member-vitals assume acf_get_field succeeds; return WP_Error otherwise.
