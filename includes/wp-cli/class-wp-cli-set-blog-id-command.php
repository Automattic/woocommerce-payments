<?php
/**
 * Set Blog ID command.
 *
 * @package WooCommerce\Payments
 */

/**
 * Set Blog ID command.
 */
class WP_CLI_Set_Blog_Id_Command {
	/**
	 * Sets fake Jetpack options required to send requests to the server on behalf of the blog id.
	 *
	 * Available only in the development environment.
	 *
	 * ## OPTIONS
	 *
	 * <blog_id>
	 * : The blog ID.
	 *
	 * [--blog_token=<value>]
	 * : Jetpack blog token. Values should be wrapped in quotes.
	 *
	 * [--user_token=<value>]
	 * : Jetpack user token. Values should be wrapped in quotes.
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Update Blog ID
	 *     wp woopayments set_blog_id <blog_id>
	 *
	 *     # Update Blog ID with blog & user tokens
	 *     wp woopayments set_blog_id <blog_id> --blog_token=<value> --user_token=<value>
	 *
	 * @when after_wp_load
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$blog_id = $args[0];
		if ( ! is_numeric( $blog_id ) ) {
			WP_CLI::error( 'Please provide a numeric blog ID.' );
		}

		if ( ! class_exists( 'Jetpack_Options' ) ) {
			WP_CLI::error( 'Jetpack_Options class does not exist. Please check your Jetpack installation.' );
		}

		$blog_token = ! empty( $assoc_args['blog_token'] ) ? $assoc_args['blog_token'] : '123.ABC';
		$user_token = [
			1 => ! empty( $assoc_args['user_token'] ) ? $assoc_args['user_token'] : '123.ABC.1',
		];

		Jetpack_Options::update_option( 'id', intval( $blog_id ) );
		Jetpack_Options::update_option( 'master_user', 1 );
		Jetpack_Options::update_option( 'blog_token', $blog_token );
		Jetpack_Options::update_option( 'user_tokens', $user_token );

		WP_CLI::success( "Set Jetpack blog id to $blog_id" );
	}
}
