<?php
/**
 * New post / story — a simple, phone-first composer. Driven by assets/js/compose.js.
 * Override by copying to <theme>/instopress-social/compose.php.
 */

defined( 'ABSPATH' ) || exit;

$kind   = isset( $_GET['type'] ) && in_array( $_GET['type'], array( 'story', 'text' ), true ) ? sanitize_key( $_GET['type'] ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification
$text   = iar_text_presets();
$labels = iar_ratio_labels();
unset( $labels['original'] );

iar_template_part( 'header', array( 'topbar_title' => __( 'New post', 'instopress-social' ), 'back' => true ) );
?>
		<div class="container compose" data-compose data-kind="<?php echo esc_attr( $kind ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'iar_compose' ) ); ?>"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-max-upload="<?php echo esc_attr( wp_max_upload_size() ); ?>"
			data-text-presets="<?php echo esc_attr( wp_json_encode( $text ) ); ?>">

			<header class="compose__bar">
				<a class="compose__cancel" href="<?php echo esc_url( iar_profile_url() ); ?>"><?php esc_html_e( 'Cancel', 'instopress-social' ); ?></a>
				<div class="compose__kinds" role="tablist">
					<button type="button" role="tab" data-kind-btn="post" aria-selected="<?php echo 'post' === $kind ? 'true' : 'false'; ?>"><?php esc_html_e( 'Post', 'instopress-social' ); ?></button>
					<button type="button" role="tab" data-kind-btn="text" aria-selected="<?php echo 'text' === $kind ? 'true' : 'false'; ?>"><?php esc_html_e( 'Text', 'instopress-social' ); ?></button>
					<button type="button" role="tab" data-kind-btn="story" aria-selected="<?php echo 'story' === $kind ? 'true' : 'false'; ?>"><?php esc_html_e( 'Story', 'instopress-social' ); ?></button>
				</div>
				<button type="button" class="compose__share" data-share disabled><?php esc_html_e( 'Share', 'instopress-social' ); ?></button>
			</header>

			<input type="file" data-file-input accept="image/*,video/*" multiple hidden>

			<div class="compose__empty" data-empty>
				<button type="button" class="compose__pick" data-pick>
					<?php echo iar_icon( 'create' ); // phpcs:ignore ?>
					<span data-pick-label><?php esc_html_e( 'Choose photos & videos', 'instopress-social' ); ?></span>
				</button>
				<p class="compose__hint" data-hint-post><?php esc_html_e( 'Up to 20 — they become a carousel in the order you pick.', 'instopress-social' ); ?></p>
				<p class="compose__hint" data-hint-story hidden><?php esc_html_e( 'One photo or video. It disappears after the story time set in Settings.', 'instopress-social' ); ?></p>
			</div>

			<div class="compose__editor" data-editor hidden>
				<div class="compose__preview" data-preview data-for="post story"></div>

				<ul class="compose__strip" data-strip data-for="post story"></ul>

				<?php /* Text post: live card, rich text box, look. Drawn by compose.js the same way includes/text-posts.php does. */ ?>
				<div class="compose__text" data-for="text" hidden>
					<div class="compose__preview compose__preview--text" data-text-preview></div>

					<div class="compose__toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'instopress-social' ); ?>">
						<?php
						$tools = array(
							'bold'                => array( '<b>B</b>', __( 'Bold', 'instopress-social' ) ),
							'italic'              => array( '<i>I</i>', __( 'Italic', 'instopress-social' ) ),
							'underline'           => array( '<u>U</u>', __( 'Underline', 'instopress-social' ) ),
							'strikeThrough'       => array( '<s>S</s>', __( 'Strikethrough', 'instopress-social' ) ),
							'h2'                  => array( 'H', __( 'Heading', 'instopress-social' ) ),
							'blockquote'          => array( '&ldquo;', __( 'Quote', 'instopress-social' ) ),
							'insertUnorderedList' => array( '&bull;', __( 'Bulleted list', 'instopress-social' ) ),
							'insertOrderedList'   => array( '1.', __( 'Numbered list', 'instopress-social' ) ),
							'createLink'          => array( '&#128279;', __( 'Link', 'instopress-social' ) ),
							'removeFormat'        => array( '&#10005;', __( 'Clear formatting', 'instopress-social' ) ),
						);
						foreach ( $tools as $cmd => $tool ) :
							?>
							<button type="button" data-cmd="<?php echo esc_attr( $cmd ); ?>" title="<?php echo esc_attr( $tool[1] ); ?>" aria-label="<?php echo esc_attr( $tool[1] ); ?>"><?php echo $tool[0]; // phpcs:ignore -- fixed markup ?></button>
						<?php endforeach; ?>
					</div>
					<div class="compose__rich" data-text-input contenteditable="true" role="textbox" aria-multiline="true" aria-label="<?php esc_attr_e( 'Your text', 'instopress-social' ); ?>" data-placeholder="<?php esc_attr_e( 'What\'s on your mind?', 'instopress-social' ); ?>"></div>

					<div class="compose__field">
						<span class="compose__label"><?php esc_html_e( 'Background', 'instopress-social' ); ?></span>
						<div class="compose__swatches">
							<?php foreach ( $text['backgrounds'] as $key => $bg ) : ?>
								<button type="button" class="swatch" data-text-bg="<?php echo esc_attr( $key ); ?>" title="<?php echo esc_attr( $bg['label'] ); ?>" aria-label="<?php echo esc_attr( $bg['label'] ); ?>"
									style="background: <?php echo esc_attr( count( $bg['colors'] ) > 1 ? 'linear-gradient(135deg, ' . implode( ', ', $bg['colors'] ) . ')' : $bg['colors'][0] ); ?>"></button>
							<?php endforeach; ?>
							<label class="swatch swatch--custom" title="<?php esc_attr_e( 'Custom colour', 'instopress-social' ); ?>"><input type="color" data-text-bg-custom value="#3a86ff" aria-label="<?php esc_attr_e( 'Custom background colour', 'instopress-social' ); ?>"></label>
						</div>
					</div>
					<div class="compose__field">
						<span class="compose__label"><?php esc_html_e( 'Text colour', 'instopress-social' ); ?></span>
						<div class="compose__swatches">
							<button type="button" class="swatch swatch--auto" data-text-ink="" title="<?php esc_attr_e( 'Automatic', 'instopress-social' ); ?>" aria-label="<?php esc_attr_e( 'Automatic', 'instopress-social' ); ?>">A</button>
							<?php foreach ( $text['inks'] as $ink ) : ?>
								<button type="button" class="swatch" data-text-ink="<?php echo esc_attr( $ink ); ?>" aria-label="<?php echo esc_attr( $ink ); ?>" style="background: <?php echo esc_attr( $ink ); ?>"></button>
							<?php endforeach; ?>
							<label class="swatch swatch--custom" title="<?php esc_attr_e( 'Custom colour', 'instopress-social' ); ?>"><input type="color" data-text-ink-custom value="#ffffff" aria-label="<?php esc_attr_e( 'Custom text colour', 'instopress-social' ); ?>"></label>
						</div>
					</div>
					<div class="compose__field">
						<span class="compose__label"><?php esc_html_e( 'Text size', 'instopress-social' ); ?> <em data-text-scale-label>100%</em></span>
						<input type="range" class="compose__range" data-text-scale min="50" max="200" step="5" value="100" aria-label="<?php esc_attr_e( 'Text size', 'instopress-social' ); ?>">
					</div>
					<div class="compose__field">
						<span class="compose__label"><?php esc_html_e( 'Font and alignment', 'instopress-social' ); ?></span>
						<div class="compose__chips">
							<?php foreach ( $text['fonts'] as $key => $font ) : ?>
								<button type="button" class="chip" data-text-font="<?php echo esc_attr( $key ); ?>" style="font-family: <?php echo esc_attr( $font['stack'] ); ?>; font-weight: <?php echo (int) $font['weight']; ?>"><?php echo esc_html( $font['label'] ); ?></button>
							<?php endforeach; ?>
							<span class="compose__chips-break" aria-hidden="true"></span>
							<?php
							// The usual "lines of text" alignment icons: x1/x2 of each line, top to bottom.
							$aligns = array(
								'left'   => array( __( 'Align left', 'instopress-social' ), array( array( 3, 21 ), array( 3, 15 ), array( 3, 21 ), array( 3, 13 ) ) ),
								'center' => array( __( 'Align centre', 'instopress-social' ), array( array( 3, 21 ), array( 6, 18 ), array( 3, 21 ), array( 7, 17 ) ) ),
								'right'  => array( __( 'Align right', 'instopress-social' ), array( array( 3, 21 ), array( 9, 21 ), array( 3, 21 ), array( 11, 21 ) ) ),
							);
							foreach ( $aligns as $align => $a ) :
								?>
								<button type="button" class="chip chip--icon" data-text-align="<?php echo esc_attr( $align ); ?>" title="<?php echo esc_attr( $a[0] ); ?>" aria-label="<?php echo esc_attr( $a[0] ); ?>">
									<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><?php foreach ( $a[1] as $i => $line ) : ?><line x1="<?php echo (int) $line[0]; ?>" y1="<?php echo (int) ( 5 + $i * 5 ); ?>" x2="<?php echo (int) $line[1]; ?>" y2="<?php echo (int) ( 5 + $i * 5 ); ?>"/><?php endforeach; ?></svg>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="compose__field" data-for="post text">
					<span class="compose__label"><?php esc_html_e( 'Shape', 'instopress-social' ); ?></span>
					<div class="compose__chips" data-ratios>
						<?php foreach ( $labels as $value => $label ) : ?>
							<button type="button" class="chip" data-ratio="<?php echo esc_attr( $value ); ?>" aria-pressed="<?php echo 'auto' === $value ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<label class="compose__field" data-for="post text">
					<span class="compose__label"><?php esc_html_e( 'Caption', 'instopress-social' ); ?></span>
					<textarea name="caption" rows="4" maxlength="2200" placeholder="<?php esc_attr_e( 'Write a caption… #hashtags @mentions', 'instopress-social' ); ?>"></textarea>
				</label>
				<label class="compose__field" data-for="post text">
					<span class="compose__label"><?php esc_html_e( 'Location', 'instopress-social' ); ?> <em><?php esc_html_e( '(optional)', 'instopress-social' ); ?></em></span>
					<input type="text" name="location" maxlength="100" autocomplete="off">
				</label>

				<label class="compose__field" data-for="story" hidden>
					<span class="compose__label"><?php esc_html_e( 'Text on the story', 'instopress-social' ); ?> <em><?php esc_html_e( '(optional)', 'instopress-social' ); ?></em></span>
					<input type="text" name="story_text" maxlength="140" autocomplete="off">
				</label>
				<label class="compose__field" data-for="story" hidden>
					<span class="compose__label"><?php esc_html_e( 'Link', 'instopress-social' ); ?> <em><?php esc_html_e( '(optional)', 'instopress-social' ); ?></em></span>
					<input type="url" name="story_link" placeholder="https://" autocomplete="off" inputmode="url">
				</label>
			</div>

			<div class="compose__progress" data-progress hidden role="status" aria-live="polite">
				<div class="compose__progress-box">
					<div class="compose__bar-track"><div class="compose__bar-fill" data-progress-fill></div></div>
					<p data-progress-text><?php esc_html_e( 'Uploading…', 'instopress-social' ); ?></p>
				</div>
			</div>
		</div>
<?php
iar_template_part( 'footer' );
