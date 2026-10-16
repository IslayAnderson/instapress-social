<?php
/**
 * New post / story — a simple, phone-first composer. Driven by assets/js/compose.js.
 * Override by copying to <theme>/instapress-social/compose.php.
 */

defined( 'ABSPATH' ) || exit;

$kind   = ( isset( $_GET['type'] ) && 'story' === $_GET['type'] ) ? 'story' : 'post'; // phpcs:ignore WordPress.Security.NonceVerification
$labels = iar_ratio_labels();
unset( $labels['original'] );

iar_template_part( 'header', array( 'topbar_title' => __( 'New post', 'instapress-social' ), 'back' => true ) );
?>
		<div class="container compose" data-compose data-kind="<?php echo esc_attr( $kind ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'iar_compose' ) ); ?>"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-max-upload="<?php echo esc_attr( wp_max_upload_size() ); ?>">

			<header class="compose__bar">
				<a class="compose__cancel" href="<?php echo esc_url( iar_profile_url() ); ?>"><?php esc_html_e( 'Cancel', 'instapress-social' ); ?></a>
				<div class="compose__kinds" role="tablist">
					<button type="button" role="tab" data-kind-btn="post" aria-selected="<?php echo 'post' === $kind ? 'true' : 'false'; ?>"><?php esc_html_e( 'Post', 'instapress-social' ); ?></button>
					<button type="button" role="tab" data-kind-btn="story" aria-selected="<?php echo 'story' === $kind ? 'true' : 'false'; ?>"><?php esc_html_e( 'Story', 'instapress-social' ); ?></button>
				</div>
				<button type="button" class="compose__share" data-share disabled><?php esc_html_e( 'Share', 'instapress-social' ); ?></button>
			</header>

			<input type="file" data-file-input accept="image/*,video/*" multiple hidden>

			<div class="compose__empty" data-empty>
				<button type="button" class="compose__pick" data-pick>
					<?php echo iar_icon( 'create' ); // phpcs:ignore ?>
					<span data-pick-label><?php esc_html_e( 'Choose photos & videos', 'instapress-social' ); ?></span>
				</button>
				<p class="compose__hint" data-hint-post><?php esc_html_e( 'Up to 20 — they become a carousel in the order you pick.', 'instapress-social' ); ?></p>
				<p class="compose__hint" data-hint-story hidden><?php esc_html_e( 'One photo or video. It disappears after the story time set in Settings.', 'instapress-social' ); ?></p>
			</div>

			<div class="compose__editor" data-editor hidden>
				<div class="compose__preview" data-preview></div>

				<ul class="compose__strip" data-strip></ul>

				<div class="compose__field" data-for="post">
					<span class="compose__label"><?php esc_html_e( 'Shape', 'instapress-social' ); ?></span>
					<div class="compose__chips" data-ratios>
						<?php foreach ( $labels as $value => $label ) : ?>
							<button type="button" class="chip" data-ratio="<?php echo esc_attr( $value ); ?>" aria-pressed="<?php echo 'auto' === $value ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<label class="compose__field" data-for="post">
					<span class="compose__label"><?php esc_html_e( 'Caption', 'instapress-social' ); ?></span>
					<textarea name="caption" rows="4" maxlength="2200" placeholder="<?php esc_attr_e( 'Write a caption… #hashtags @mentions', 'instapress-social' ); ?>"></textarea>
				</label>
				<label class="compose__field" data-for="post">
					<span class="compose__label"><?php esc_html_e( 'Location', 'instapress-social' ); ?> <em><?php esc_html_e( '(optional)', 'instapress-social' ); ?></em></span>
					<input type="text" name="location" maxlength="100" autocomplete="off">
				</label>

				<label class="compose__field" data-for="story" hidden>
					<span class="compose__label"><?php esc_html_e( 'Text on the story', 'instapress-social' ); ?> <em><?php esc_html_e( '(optional)', 'instapress-social' ); ?></em></span>
					<input type="text" name="story_text" maxlength="140" autocomplete="off">
				</label>
				<label class="compose__field" data-for="story" hidden>
					<span class="compose__label"><?php esc_html_e( 'Link', 'instapress-social' ); ?> <em><?php esc_html_e( '(optional)', 'instapress-social' ); ?></em></span>
					<input type="url" name="story_link" placeholder="https://" autocomplete="off" inputmode="url">
				</label>
			</div>

			<div class="compose__progress" data-progress hidden role="status" aria-live="polite">
				<div class="compose__progress-box">
					<div class="compose__bar-track"><div class="compose__bar-fill" data-progress-fill></div></div>
					<p data-progress-text><?php esc_html_e( 'Uploading…', 'instapress-social' ); ?></p>
				</div>
			</div>
		</div>
<?php
iar_template_part( 'footer' );
