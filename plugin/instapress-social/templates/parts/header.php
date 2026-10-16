<?php
/**
 * Document start and top bar (mobile).
 *
 * @var array $args { @type string $topbar_title, @type bool $back }
 */

defined( 'ABSPATH' ) || exit;

$profile_url = iar_profile_url();
$username    = iar_setting( 'username' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
		<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site">
	<main class="main">
		<div class="topbar">
			<?php if ( ! empty( $args['back'] ) ) : ?>
				<a href="<?php echo esc_url( $profile_url ); ?>" aria-label="<?php esc_attr_e( 'Back', 'instapress-social' ); ?>"><?php echo iar_icon( 'chevron-left' ); // phpcs:ignore ?></a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<span class="topbar__title"><?php echo esc_html( $args['topbar_title'] ?? $username ); ?></span>
			<span></span>
		</div>
