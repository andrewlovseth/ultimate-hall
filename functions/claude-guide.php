<?php
/**
 * wp-admin > Claude: a read-only guide to editing the Hall with Claude.
 *
 * Setup for claude.ai, the apps and Claude Code; the editor's own connection;
 * what to ask; every tool in plain words; and the safety rules. The tool table
 * comes from uh_ability_names(), so a tool added there shows up here, with its
 * registered description until it gets a plain-words line in uh_guide_tools().
 */

/** Plain-words line per tool, grouped as uh_ability_names() orders them. */
function uh_guide_tools() {
	return array(
		'Looking things up'               => array(
			'find-member'     => 'Finds members by full or partial name, drafts included.',
			'get-member'      => 'Shows everything on a member: vitals, class, bio, photos, career, results, service and awards, each row numbered.',
			'list-class'      => 'Lists a class\'s members and what each one is still missing.',
			'find-team'       => 'Finds teams by name, nickname or alias, with city and division.',
			'get-team'        => 'Shows a team and every member who played for it.',
			'find-tournament' => 'Finds tournaments by name, year and series.',
			'get-tournament'  => 'Shows a tournament and every member result in it.',
			'list-divisions'  => 'Lists divisions, for creating teams.',
			'find-media'      => 'Searches the media library for photos.',
		),
		'Members'                         => array(
			'create-member'        => 'Adds a new member as a draft, refusing near-duplicates.',
			'update-member'        => 'Changes name, web address, class, division or induction type; publishes or unpublishes.',
			'update-member-vitals' => 'Changes hometown, birthdate or date of death.',
			'set-bio'              => 'Writes the biography, cleaned to simple formatting.',
			'set-photo'            => 'Sets the headshot or player card from the library or an image link.',
			'add-career-stint'     => 'Adds one stint on a team, with its years.',
			'add-result'           => 'Adds one tournament appearance: tournament, team, placement.',
			'add-service'          => 'Adds one contribution or service entry, with its years.',
			'add-award'            => 'Adds one award, with its year.',
			'update-row'           => 'Changes fields on one numbered row.',
			'remove-row'           => 'Deletes one numbered row.',
		),
		'Teams, tournaments and classes' => array(
			'create-team'       => 'Creates a team, refusing near-duplicates in the same division.',
			'update-team'       => 'Changes a team\'s name, city, division or aliases.',
			'create-tournament' => 'Creates a tournament, refusing near-duplicates in the same year.',
			'update-tournament' => 'Changes a tournament\'s name, year, series or location.',
			'create-year'       => 'Creates a year page, and optionally adds it as a class on the Members page.',
			'merge'             => 'Combines duplicate teams or tournaments: shows what would move, then does it when you confirm.',
			'trash'             => 'Moves a member, team or tournament to the trash; refuses one that members still use.',
		),
		'History and undo'               => array(
			'recent-changes' => 'Lists the latest changes across the site.',
			'member-history' => 'Lists recent changes to one member, team or tournament.',
			'undo-change'    => 'Reverses one change, including merges and trashes.',
		),
	);
}

/** Every registered tool, grouped; tools with no plain-words line fall back to their registered description. */
function uh_guide_tool_rows() {
	$groups = uh_guide_tools();
	$known  = array();
	foreach ( $groups as $tools ) {
		$known += $tools;
	}
	$out = array();
	foreach ( $groups as $group => $tools ) {
		$out[ $group ] = array_intersect_key( $tools, array_flip( array_map( fn( $n ) => substr( $n, 5 ), uh_ability_names() ) ) );
	}
	foreach ( uh_ability_names() as $name ) {
		$short = substr( $name, 5 );
		if ( isset( $known[ $short ] ) ) {
			continue;
		}
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
		$out['Other'][ $short ] = $ability ? $ability->get_description() : '';
	}
	return array_filter( $out );
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Editing with Claude', 'Claude', 'edit_others_posts', 'uh-claude', 'uh_guide_page', 'dashicons-format-chat', 3 );
	}
);

function uh_guide_page() {
	$url    = uh_oauth_resource();
	$grants = uh_oauth_user_grants( get_current_user_id() );
	$code   = 'claude mcp add --transport http ultimate-hall ' . $url;
	?>
	<div class="wrap uh-guide">
		<h1>Editing the Hall with Claude</h1>
		<p class="uh-lead">Claude can look up and edit members, teams, tournaments and classes for you. It signs in as you, so it can do what your account can do and nothing more, and every change it makes is logged and can be undone.</p>

		<div class="uh-status <?php echo $grants ? 'is-on' : 'is-off'; ?>">
			<?php if ( $grants ) : ?>
				<strong>You're connected.</strong>
				<?php
				$names = array_map(
					function ( $g ) {
						$used = $g['last_used'] ? 'last used ' . human_time_diff( $g['last_used'] ) . ' ago' : 'not used yet';
						return esc_html( $g['client_name'] ) . ' (' . esc_html( $used ) . ')';
					},
					$grants
				);
				echo implode( ', ', $names ) . '. ';
				?>
				<a href="<?php echo esc_url( admin_url( 'profile.php#uh-claude-connections' ) ); ?>">Manage or disconnect</a>
			<?php else : ?>
				<strong>Not connected yet.</strong> Follow the steps below; it takes about two minutes.
			<?php endif; ?>
		</div>

		<h2>Set up</h2>
		<div class="uh-cards">
			<div class="card">
				<h3>claude.ai, desktop and phone</h3>
				<ol>
					<li>In claude.ai, open <strong>Customize</strong>, then <strong>Connectors</strong>, and click <strong>Add custom connector</strong>.</li>
					<li>Name it <em>Ultimate Hall of Fame</em> and paste this address:
						<span class="uh-copy"><input type="text" readonly value="<?php echo esc_attr( $url ); ?>" onclick="this.select()"><button type="button" class="button" data-copy="<?php echo esc_attr( $url ); ?>">Copy</button></span>
					</li>
					<li>Click <strong>Connect</strong>. This site opens; log in if it asks, then click <strong>Allow</strong>.</li>
					<li>In a new chat, check the connector is switched on in the <strong>+</strong> menu, and ask: <em>"Find the Hall of Fame member Jim Parinella."</em></li>
				</ol>
				<p class="description">Any Claude plan works, including the free one, which allows one custom connector. On a Team or Enterprise plan, an owner of that plan has to add the connector first. Once added, it also works in the Claude desktop and phone apps. If you go a month without using it, Claude asks you to sign in again.</p>
			</div>
			<div class="card">
				<h3>Claude Code</h3>
				<p>Run this in a terminal:</p>
				<span class="uh-copy"><input type="text" readonly value="<?php echo esc_attr( $code ); ?>" onclick="this.select()"><button type="button" class="button" data-copy="<?php echo esc_attr( $code ); ?>">Copy</button></span>
				<p>Then type <code>/mcp</code> in Claude Code, pick <em>ultimate-hall</em> and choose <strong>Authenticate</strong>. A browser window opens here for you to Allow.</p>
				<p class="description">Only Editors and Administrators can connect. To cut off access, disconnect on <a href="<?php echo esc_url( admin_url( 'profile.php#uh-claude-connections' ) ); ?>">your profile</a> or remove the connector in Claude; changing your password also disconnects every Claude app.</p>
			</div>
		</div>

		<h2>What to ask</h2>
		<p>Talk to it the way you'd brief a helper. It picks the tools, reads the record first, and tells you what it changed.</p>
		<ul class="uh-prompts">
			<li>"She won the 2003 Club Championships with Fury. Add that to Leslie Calder."</li>
			<li>"Add Sam Example to the 2026 class as a player in the Women's division, from this bio: ..."</li>
			<li>"What is the 2026 class still missing? Go through them one by one."</li>
			<li>"Boston DoG and Death or Glory look like the same team. Show me what a merge would do."</li>
			<li>"Set this as Jim Parinella's headshot: https://..."</li>
			<li>"What changed on the site today?" then "Undo the last change to Leslie Calder."</li>
		</ul>
		<p>For more than a handful of edits, ask Claude to list what it will change and wait for your OK before it starts.</p>

		<h2>The tools</h2>
		<p>Claude chooses these itself; you never need to name them. They are listed so you know what it can and can't do.</p>
		<table class="widefat striped uh-tools">
			<thead><tr><th scope="col">Tool</th><th scope="col">What it does</th></tr></thead>
			<tbody>
			<?php foreach ( uh_guide_tool_rows() as $group => $tools ) : ?>
				<tr class="uh-group"><th colspan="2" scope="rowgroup"><?php echo esc_html( $group ); ?></th></tr>
				<?php foreach ( $tools as $name => $text ) : ?>
					<tr><td><code><?php echo esc_html( $name ); ?></code></td><td><?php echo esc_html( $text ); ?></td></tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>Claude can't empty the trash, change users or settings, or edit pages and events. Those stay here in wp-admin.</p>

		<h2>Working safely</h2>
		<p>Changes go live as soon as Claude makes them, so the tools are built to catch the common mistakes.</p>
		<ul class="uh-list">
			<li><strong>Look up before adding.</strong> Adding a row refuses an exact duplicate. Creating a member, team or tournament stops if a similar one exists and shows it; Claude goes ahead only when you confirm they really are different.</li>
			<li><strong>New members start as drafts.</strong> They stay hidden until you ask Claude to publish, so check the record first.</li>
			<li><strong>Rows can't shift under it.</strong> If someone else edits a member while Claude is working, its next row edit is refused and it reads the member again.</li>
			<li><strong>Undo.</strong> Every change is logged on the member, team or tournament. Merges and trashes can be undone too. Undoing an older change also rolls back later changes to the same list, so undo newest first.</li>
			<li><strong>Private fields stay private.</strong> Birthdates, dates of death, interviews and letters of recommendation are hidden from anyone not logged in.</li>
		</ul>

		<h2>How the data fits together</h2>
		<ul class="uh-list">
			<li><strong>Career:</strong> one row per continuous stint on a team, with a start year, an optional end year, and "to present" and "approximate" flags. A player on Sockeye 1999–2002 and again in 2005 gets two rows.</li>
			<li><strong>Results:</strong> one row per tournament appearance: the tournament, the team, and a placement from a fixed list.</li>
			<li><strong>Service:</strong> board seats, coaching, tournament direction and similar, each with years and a description.</li>
			<li><strong>Awards:</strong> one row per award, with its year.</li>
		</ul>
		<p>Teams and tournaments don't store their players. Their pages are built from members' rows, so adding a result to a member updates the team and tournament pages too. Each tournament has a series (U.S., World or Pro) that decides which table it shows in, and is named "year + event", such as "1995 UPA Club Championships". Add the names people actually use to a team's or tournament's aliases ("DoG", "Boston DoG") so lookups find it instead of creating a duplicate.</p>
	</div>
	<style>
		.uh-guide { max-width: 1000px; }
		.uh-guide .uh-lead { font-size: 15px; max-width: 70ch; }
		.uh-guide h2 { margin-top: 2em; }
		.uh-guide .uh-status { background: #fff; border-left: 4px solid #dba617; padding: 10px 14px; margin: 1em 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
		.uh-guide .uh-status.is-on { border-left-color: #00a32a; }
		.uh-guide .uh-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
		.uh-guide .card { max-width: none; margin: 0; }
		.uh-guide .card h3 { margin-top: .5em; }
		.uh-guide .card li { margin-bottom: .6em; }
		.uh-guide .uh-copy { display: flex; gap: 6px; margin: 6px 0; }
		.uh-guide .uh-copy input { flex: 1; font-family: Consolas, Monaco, monospace; font-size: 12px; }
		.uh-guide .uh-prompts li { font-style: italic; }
		.uh-guide .uh-list, .uh-guide .uh-prompts { list-style: disc; padding-left: 1.5em; max-width: 80ch; }
		.uh-guide .uh-tools td:first-child { width: 190px; white-space: nowrap; }
		.uh-guide .uh-tools .uh-group th { background: #f0f0f1; font-weight: 600; }
	</style>
	<script>
		document.querySelectorAll('.uh-guide [data-copy]').forEach(function (b) {
			b.addEventListener('click', function () {
				navigator.clipboard.writeText(b.dataset.copy).then(function () {
					b.textContent = 'Copied';
					setTimeout(function () { b.textContent = 'Copy'; }, 1500);
				});
			});
		});
	</script>
	<?php
}
