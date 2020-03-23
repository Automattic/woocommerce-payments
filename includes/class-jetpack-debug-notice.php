<?php

defined( 'ABSPATH' ) || exit;

/**
 * A place to put all the in-progress logic of the Jetpack connection. Most of this will probably need to end up having
 * its own stand-alone UI, but for now it's just a notice with a bunch of text & buttons. Deal with it.
 */
class Jetpack_Debug_Notice {

	private static $manager;

	public static function init( $manager ) {
		self::$manager = $manager;

		add_filter( 'admin_notices', array( __CLASS__, 'show_notice' ) );
		add_action( 'admin_post_jetpack_wcpay_register_site', array( __CLASS__, 'register_site' ) );
		add_action( 'admin_post_jetpack_wcpay_connect_user', array( __CLASS__, 'connect_user' ) );
		add_action( 'admin_post_jetpack_wcpay_disconnect_user', array( __CLASS__, 'disconnect_user' ) );
		add_action( 'admin_post_jetpack_wcpay_disconnect_site', array( __CLASS__, 'disconnect_site' ) );
	}

	public static function show_notice() {
		$current_user_token = self::$manager->get_access_token( get_current_user_id() );
		$master_user_token = self::$manager->get_access_token( Automattic\Jetpack\Connection\Manager::JETPACK_MASTER_USER );
		$blog_token = self::$manager->get_access_token();
		?>
		<div id="woocommerce_errors" class="notice">
			<?php if ( $blog_token ) : ?>
				This site is connected to Jetpack. Blog token: <?php echo var_export( $blog_token, true ); ?>
				<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
					<input type="hidden" name="action" value="jetpack_wcpay_disconnect_site">
					<?php wp_nonce_field( 'disconnect-site' ); ?>
					<input type="submit" value="Disconnect site">
				</form>
				<?php if ( $current_user_token ) : ?>
					The current user is connected to Jetpack. User token: <?php echo var_export( $current_user_token, true ); ?>
					<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
						<input type="hidden" name="action" value="jetpack_wcpay_disconnect_user">
						<?php wp_nonce_field( 'disconnect-user' ); ?>
						<input type="submit" value="Disconnect current user">
					</form>
				<?php else: ?>
					The current user is NOT connected to Jetpack.
					<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
						<input type="hidden" name="action" value="jetpack_wcpay_connect_user">
						<?php wp_nonce_field( 'connect-user' ); ?>
						<input type="submit" value="Connect current user">
					</form>
				<?php endif; ?>
				<?php if ( $current_user_token && $master_user_token ) : ?>
					<?php if ( $current_user_token->external_user_id !== $master_user_token->external_user_id ) : ?>
						The current user is NOT the Jetpack master user. The master user's token is: <?php echo var_export( $master_user_token, true ); ?>
					<?php endif; ?>
				<?php endif; ?>
			<?php else: ?>
				This site is NOT connected to Jetpack.
				<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
					<input type="hidden" name="action" value="jetpack_wcpay_register_site">
					<?php wp_nonce_field( 'register-site' ); ?>
					<input type="submit" value="Register this site">
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	// These actions below have been lifted directly from the "client-example" plugin

	/**
	 * Registers the site using the connection package.
	 */
	public static function register_site() {
		check_admin_referer( 'register-site' );
		$result = self::$manager->register();
		if ( is_wp_error( $result ) ) {
			echo $result->get_error_message();
			die();
		}
		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}
	/**
	 * Connects the currently logged in user.
	 */
	public static function connect_user() {
		check_admin_referer( 'connect-user' );
		$result = self::$manager->connect_user();
		if ( is_wp_error( $result ) ) {
			echo $result->get_error_message();
			die();
		}
		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}
	/**
	 * Disconnects the currently logged in user.
	 */
	public static function disconnect_user() {
		check_admin_referer( 'disconnect-user' );
		$result = self::$manager->disconnect_user( get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			echo $result->get_error_message();
			die();
		}
		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}
	/**
	 * Disconnects the site.
	 */
	public static function disconnect_site() {
		check_admin_referer( 'disconnect-site' );
		$result = self::$manager->disconnect_site_wpcom();
		if ( is_wp_error( $result ) ) {
			echo 'Error disconnecting site: ' . $result->get_error_message();
			die();
		}
		$result = self::$manager->delete_all_connection_tokens();
		if ( is_wp_error( $result ) ) {
			echo 'Error deleting tokens: ' . $result->get_error_message();
			die();
		}
		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}
}
