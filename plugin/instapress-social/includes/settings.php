<?php
/**
 * Settings → instapress-social: profile header details + template options.
 */

defined( 'ABSPATH' ) || exit;

const IAR_OPTION = 'iar_settings';

function iar_defaults() {
	return array(
		'username'        => 'islayanderson64',
		'name'            => get_bloginfo( 'name' ),
		'bio'             => '',
		'website'         => home_url( '/' ),
		'avatar_id'       => 0,
		'followers'       => '',
		'following'       => '',
		'archive_slug'    => 'social',
		'post_slug'       => 'p',
		'per_page'        => 24,
		'grid_ratio'      => '1:1',
		'use_templates'   => 1,
		'isolate_styles'  => 1,
		'hide_admin_bar'  => 1,
		'private_home'    => 1,
		'private_follows' => 0,
		'usage_stats'     => 1,
		'share_plugin'    => 1,
		'allow_comments'  => 1,
		'allow_likes'     => 1,
		'captcha_provider' => '',
		'ts_sitekey'      => '',
		'rc3_sitekey'     => '',
		'rc2_sitekey'     => '',
		'rc3_threshold'   => 0.5,
		'ts_comments'     => 1,
		'ts_likes'        => 1,
		'story_hours'     => 24,
		'story_after'     => 'archive',
	);
}

/**
 * Defaults changed in 1.22.0 (profile folder /social/, toolbar hidden, home
 * feed private). Sites that were already running keep what they had: any of
 * these they never saved are pinned to the old default, once, so an update
 * doesn't move their profile address or change who can see what.
 */
function iar_pin_previous_defaults() {
	if ( get_option( 'iar_defaults_pinned' ) ) {
		return;
	}
	// Running an older version before this one, or (very old installs) has settings or posts already.
	$was      = (string) get_option( 'iar_version' );
	$existing = $was ? version_compare( $was, '1.22.0', '<' ) : ( (bool) get_option( IAR_OPTION ) || (int) wp_count_posts( IAR_POST_TYPE )->publish > 0 );
	if ( $existing ) {
		$saved = (array) get_option( IAR_OPTION, array() );
		$saved += array( 'archive_slug' => 'gram', 'hide_admin_bar' => 0, 'private_home' => 0 );
		$GLOBALS['iar_pinning_defaults'] = true; // not a real change: no "folder changed" redirect
		update_option( IAR_OPTION, $saved );
		unset( $GLOBALS['iar_pinning_defaults'] );
	}
	update_option( 'iar_defaults_pinned', 1, false );
}
// Before the post type and routes are registered (init 10).
add_action( 'init', 'iar_pin_previous_defaults', 1 );

function iar_settings() {
	return wp_parse_args( (array) get_option( IAR_OPTION, array() ), iar_defaults() );
}

function iar_setting( $key ) {
	$s = iar_settings();
	return $s[ $key ] ?? null;
}

add_action( 'admin_menu', function () {
	add_options_page( __( 'instapress-social', 'instapress-social' ), __( 'instapress-social', 'instapress-social' ), 'manage_options', 'instapress-social', 'iar_render_settings' );
	// Shortcut under the post type menu too.
	add_submenu_page( 'edit.php?post_type=' . IAR_POST_TYPE, __( 'Settings', 'instapress-social' ), __( 'Settings', 'instapress-social' ), 'manage_options', 'options-general.php?page=instapress-social' );
} );

add_action( 'admin_init', function () {
	register_setting( 'instapress-social', IAR_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => function ( $in ) {
			$in = (array) $in;
			return iar_sanitize_slugs( $in ) + array(
				'username'       => sanitize_user( $in['username'] ?? '', true ),
				'name'           => sanitize_text_field( $in['name'] ?? '' ),
				'bio'            => sanitize_textarea_field( $in['bio'] ?? '' ),
				'website'        => esc_url_raw( $in['website'] ?? '' ),
				'avatar_id'      => absint( $in['avatar_id'] ?? 0 ),
				'followers'      => sanitize_text_field( $in['followers'] ?? '' ),
				'following'      => sanitize_text_field( $in['following'] ?? '' ),
				'per_page'       => max( 3, min( 99, absint( $in['per_page'] ?? 24 ) ) ),
				'grid_ratio'     => in_array( $in['grid_ratio'] ?? '', array( '1:1', '3:4', '4:5' ), true ) ? $in['grid_ratio'] : '1:1',
				'use_templates'  => empty( $in['use_templates'] ) ? 0 : 1,
				'isolate_styles' => empty( $in['isolate_styles'] ) ? 0 : 1,
				'hide_admin_bar' => empty( $in['hide_admin_bar'] ) ? 0 : 1,
				'private_home'   => empty( $in['private_home'] ) ? 0 : 1,
				'private_follows' => empty( $in['private_follows'] ) ? 0 : 1,
				'usage_stats'    => empty( $in['usage_stats'] ) ? 0 : 1,
				'share_plugin'   => empty( $in['share_plugin'] ) ? 0 : 1,
				'allow_comments' => empty( $in['allow_comments'] ) ? 0 : 1,
				'allow_likes'    => empty( $in['allow_likes'] ) ? 0 : 1,
				'captcha_provider' => in_array( $in['captcha_provider'] ?? '', array( 'off', 'turnstile', 'recaptcha_v3', 'recaptcha_v2' ), true ) ? $in['captcha_provider'] : 'off',
				'ts_sitekey'     => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $in['ts_sitekey'] ?? '' ) ),
				'rc3_sitekey'    => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $in['rc3_sitekey'] ?? '' ) ),
				'rc2_sitekey'    => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $in['rc2_sitekey'] ?? '' ) ),
				'rc3_threshold'  => max( 0.1, min( 1.0, round( (float) ( $in['rc3_threshold'] ?? 0.5 ), 1 ) ) ),
				'ts_comments'    => empty( $in['ts_comments'] ) ? 0 : 1,
				'ts_likes'       => empty( $in['ts_likes'] ) ? 0 : 1,
				'story_hours'    => max( 1, min( 336, absint( $in['story_hours'] ?? 24 ) ) ),
				'story_after'    => 'delete' === ( $in['story_after'] ?? '' ) ? 'delete' : 'archive',
			);
		},
	) );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'settings_page_instapress-social' === $hook ) {
		wp_enqueue_media();
	}
} );

function iar_render_settings() {
	$s      = iar_settings();
	$name   = IAR_OPTION;
	$avatar = iar_avatar_url( 'thumbnail' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'instapress-social', 'instapress-social' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: archive URL */
				esc_html__( 'Your archive lives at %s.', 'instapress-social' ),
				'<a href="' . esc_url( get_post_type_archive_link( IAR_POST_TYPE ) ) . '">' . esc_html( get_post_type_archive_link( IAR_POST_TYPE ) ) . '</a>'
			);
			?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'instapress-social' ); ?>

			<h2 class="title"><?php esc_html_e( 'Profile header', 'instapress-social' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Profile picture', 'instapress-social' ); ?></th>
					<td>
						<img id="iar-avatar-preview" src="<?php echo esc_url( $avatar ); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;vertical-align:middle;background:#f0f0f1;">
						<input type="hidden" id="iar-avatar-id" name="<?php echo esc_attr( $name ); ?>[avatar_id]" value="<?php echo esc_attr( $s['avatar_id'] ); ?>">
						<button type="button" class="button" id="iar-avatar-pick"><?php esc_html_e( 'Choose image', 'instapress-social' ); ?></button>
						<p class="description"><?php esc_html_e( 'Falls back to the site icon.', 'instapress-social' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-username"><?php esc_html_e( 'Username', 'instapress-social' ); ?></label></th>
					<td><input type="text" id="iar-username" class="regular-text" name="<?php echo esc_attr( $name ); ?>[username]" value="<?php echo esc_attr( $s['username'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-name"><?php esc_html_e( 'Display name', 'instapress-social' ); ?></label></th>
					<td><input type="text" id="iar-name" class="regular-text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $s['name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-bio"><?php esc_html_e( 'Bio', 'instapress-social' ); ?></label></th>
					<td><textarea id="iar-bio" class="large-text" rows="3" name="<?php echo esc_attr( $name ); ?>[bio]"><?php echo esc_textarea( $s['bio'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-website"><?php esc_html_e( 'Website', 'instapress-social' ); ?></label></th>
					<td><input type="url" id="iar-website" class="regular-text" name="<?php echo esc_attr( $name ); ?>[website]" value="<?php echo esc_attr( $s['website'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Counts', 'instapress-social' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Followers', 'instapress-social' ); ?> <input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[followers]" value="<?php echo esc_attr( $s['followers'] ); ?>"></label>
						&nbsp;
						<label><?php esc_html_e( 'Following', 'instapress-social' ); ?> <input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[following]" value="<?php echo esc_attr( $s['following'] ); ?>"></label>
						<p class="description"><?php esc_html_e( 'Leave blank to hide. The post count is automatic.', 'instapress-social' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Templates', 'instapress-social' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Templates', 'instapress-social' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[use_templates]" value="1" <?php checked( $s['use_templates'] ); ?>> <?php esc_html_e( 'Use the instapress-social archive and post templates', 'instapress-social' ); ?></label>
						<p class="description">
							<?php
							printf(
								/* translators: %s: folder name */
								esc_html__( 'Override any template by copying it from the plugin\'s templates/ folder into %s in your theme.', 'instapress-social' ),
								'<code>instapress-social/</code>'
							);
							?>
						</p>
						<br>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[isolate_styles]" value="1" <?php checked( $s['isolate_styles'] ); ?>> <?php esc_html_e( 'Don\'t load the theme\'s stylesheets on these pages', 'instapress-social' ); ?></label>
						<br>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_admin_bar]" value="1" <?php checked( $s['hide_admin_bar'] ); ?>> <?php esc_html_e( 'Hide the WordPress toolbar on these pages', 'instapress-social' ); ?></label>
						<p class="description"><?php esc_html_e( 'For a cleaner, app-like look when you\'re logged in. wp-admin is still a tap away: the ⋯ menu on a post has "Edit in WordPress".', 'instapress-social' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Privacy', 'instapress-social' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[private_home]" value="1" <?php checked( $s['private_home'] ); ?>> <?php esc_html_e( 'Only I can see my home feed', 'instapress-social' ); ?></label>
						<p class="description"><?php esc_html_e( 'The home feed shows posts and stories from everyone you follow. When this is on, visitors are asked to log in and its links are hidden from them.', 'instapress-social' ); ?></p>
						<br>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[private_follows]" value="1" <?php checked( $s['private_follows'] ); ?>> <?php esc_html_e( 'Hide follower and following counts from visitors', 'instapress-social' ); ?></label>
						<p class="description"><?php esc_html_e( 'You (and anyone who can edit posts here) still see both.', 'instapress-social' ); ?></p>
						<br>
						<label id="iar-usage-stats"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[usage_stats]" value="1" <?php checked( $s['usage_stats'] ); ?>> <?php esc_html_e( 'Share anonymous usage statistics with the plugin\'s author', 'instapress-social' ); ?></label>
						<p class="description"><?php esc_html_e( 'Helps decide what to improve. Sent to Google Analytics: this site\'s domain, the plugin and WordPress versions, which instapress-social admin screens are opened and which actions are used (e.g. "settings saved", "post published"). Never your posts, settings values, users or visitors.', 'instapress-social' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Likes & comments', 'instapress-social' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[allow_likes]" value="1" <?php checked( $s['allow_likes'] ); ?>> <?php esc_html_e( 'Visitors can like posts (no account needed; one like per browser)', 'instapress-social' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[allow_comments]" value="1" <?php checked( $s['allow_comments'] ); ?>> <?php esc_html_e( 'Allow comments on posts', 'instapress-social' ); ?></label>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Discussion settings */
								esc_html__( 'Comments follow your %s (approval, required name/email, etc.) and are managed under Comments. Likes are listed there too, under the "Likes" type.', 'instapress-social' ),
								'<a href="' . esc_url( admin_url( 'options-discussion.php' ) ) . '">' . esc_html__( 'Discussion settings', 'instapress-social' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-story-hours"><?php esc_html_e( 'Stories', 'instapress-social' ); ?></label></th>
					<td>
						<?php
						printf(
							/* translators: %s: number input */
							esc_html__( 'Stories disappear after %s hours, then are', 'instapress-social' ),
							'<input type="number" id="iar-story-hours" class="small-text" min="1" max="336" name="' . esc_attr( $name ) . '[story_hours]" value="' . esc_attr( $s['story_hours'] ) . '">'
						);
						?>
						<select name="<?php echo esc_attr( $name ); ?>[story_after]">
							<option value="archive" <?php selected( $s['story_after'], 'archive' ); ?>><?php esc_html_e( 'kept privately in your archive', 'instapress-social' ); ?></option>
							<option value="delete" <?php selected( $s['story_after'], 'delete' ); ?>><?php esc_html_e( 'deleted (their media stays in the library)', 'instapress-social' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Share the plugin', 'instapress-social' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[share_plugin]" value="1" <?php checked( $s['share_plugin'] ); ?>> <?php esc_html_e( 'Show a "Get instapress-social" button so visitors can download this plugin and follow you back', 'instapress-social' ); ?></label>
						<p class="description"><?php printf( /* translators: %s: plugin version number */ esc_html__( 'Downloads the version installed here (%s).', 'instapress-social' ), esc_html( IAR_VERSION ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-archive-slug"><?php esc_html_e( 'Profile folder', 'instapress-social' ); ?></label></th>
					<td>
						<code><?php echo esc_html( untrailingslashit( home_url() ) ); ?>/</code><input type="text" id="iar-archive-slug" class="regular-text" style="width:12em" name="<?php echo esc_attr( $name ); ?>[archive_slug]" value="<?php echo esc_attr( $s['archive_slug'] ); ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="40" spellcheck="false" autocapitalize="off"><code>/</code>
						<p class="description"><?php esc_html_e( 'Where your profile lives; your home feed and New post page sit inside it (…/home/, …/new/).', 'instapress-social' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-post-slug"><?php esc_html_e( 'Post folder', 'instapress-social' ); ?></label></th>
					<td>
						<code><?php echo esc_html( untrailingslashit( home_url() ) ); ?>/</code><input type="text" id="iar-post-slug" class="regular-text" style="width:12em" name="<?php echo esc_attr( $name ); ?>[post_slug]" value="<?php echo esc_attr( $s['post_slug'] ); ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="40" spellcheck="false" autocapitalize="off"><code>/<?php echo esc_html( 'DYLRvc7CC0h' ); ?>/</code>
						<p class="description"><?php esc_html_e( 'Where each post lives. Lowercase letters, numbers and dashes. If you change either folder, the old addresses redirect to the new ones, so existing links keep working.', 'instapress-social' ); ?></p>
						<?php if ( has_filter( 'iar_archive_slug' ) || has_filter( 'iar_post_slug' ) ) : ?>
							<p class="description"><strong><?php esc_html_e( 'Code on this site overrides these folders (the iar_archive_slug / iar_post_slug filters).', 'instapress-social' ); ?></strong></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Grid tiles', 'instapress-social' ); ?></th>
					<td>
						<?php
						$tiles = array(
							'1:1' => __( 'Square 1:1 (classic)', 'instapress-social' ),
							'3:4' => __( 'Portrait 3:4 (modern grid)', 'instapress-social' ),
							'4:5' => __( 'Portrait 4:5', 'instapress-social' ),
						);
						foreach ( $tiles as $value => $label ) :
							?>
							<label style="margin-right:16px;"><input type="radio" name="<?php echo esc_attr( $name ); ?>[grid_ratio]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['grid_ratio'], $value ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Shape of the thumbnails on the profile grid. Posts keep their own aspect ratio when opened.', 'instapress-social' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iar-per-page"><?php esc_html_e( 'Posts per page', 'instapress-social' ); ?></label></th>
					<td><input type="number" id="iar-per-page" class="small-text" min="3" max="99" step="3" name="<?php echo esc_attr( $name ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ); ?>"></td>
				</tr>
			</table>

			<?php iar_render_captcha_settings( $s, $name ); ?>

			<?php submit_button(); ?>
		</form>

		<h2 class="title"><?php esc_html_e( 'Importing', 'instapress-social' ); ?></h2>
		<p><?php printf( esc_html__( 'Use the bookmarklet on %s to get posts.json from imginn, then:', 'instapress-social' ), '<a href="' . esc_url( iar_import_admin_url() ) . '">' . esc_html__( 'instapress-social → Import', 'instapress-social' ) . '</a>' ); // phpcs:ignore ?></p>
		<p><code>wp insta import posts.json path/to/media-folder --dry-run</code></p>
	</div>
	<script>
	(function () {
		var btn = document.getElementById('iar-avatar-pick');
		var frame;
		btn.addEventListener('click', function () {
			frame = frame || wp.media({ title: btn.textContent, library: { type: 'image' }, multiple: false });
			frame.off('select').on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				document.getElementById('iar-avatar-id').value = a.id;
				document.getElementById('iar-avatar-preview').src = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail : a).url;
			});
			frame.open();
		});
	})();
	</script>
	<?php
}

add_filter( 'plugin_action_links_' . plugin_basename( IAR_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=instapress-social' ) ) . '">' . esc_html__( 'Settings', 'instapress-social' ) . '</a>' );
	return $links;
} );
