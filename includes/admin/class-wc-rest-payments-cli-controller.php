<?php
/**
 * Class WC_REST_Payments_CLI_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for WooPayments CLI browser authentication.
 */
class WC_REST_Payments_CLI_Controller extends WP_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/cli';

	/**
	 * Pending authorization option name.
	 */
	private const OPTION_NAME = 'wcpay_cli_authorizations';

	/**
	 * Authorization and token TTL in seconds.
	 */
	private const TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Initialize admin-post hooks for browser approval.
	 */
	public static function init_admin_hooks(): void {
		add_action( 'admin_post_wcpay_cli_authorize', [ __CLASS__, 'handle_admin_authorize' ] );
		add_action( 'admin_post_nopriv_wcpay_cli_authorize', [ __CLASS__, 'redirect_admin_authorize_to_login' ] );
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/authorize',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'authorize' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/token',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'token' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Create a pending CLI browser authorization.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function authorize( WP_REST_Request $request ) {
		self::cleanup_expired_records();

		$validation = self::validate_authorize_request( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$now     = time();
		$auth_id = self::generate_random_token();
		$record  = [
			'id'           => $auth_id,
			'app_name'     => sanitize_text_field( $request->get_param( 'app_name' ) ),
			'scope'        => sanitize_key( $request->get_param( 'scope' ) ),
			'state'        => sanitize_text_field( $request->get_param( 'state' ) ),
			'callback_url' => esc_url_raw( $request->get_param( 'callback_url' ) ),
			'profile_name' => sanitize_text_field( (string) $request->get_param( 'profile_name' ) ),
			'created_at'   => $now,
			'expires_at'   => $now + self::TTL,
			'status'       => 'pending',
			'code_hash'    => null,
			'approved_by'  => 0,
			'used_at'      => 0,
		];

		self::save_record( $auth_id, $record );

		return rest_ensure_response(
			[
				'authorize_url' => self::get_authorize_login_url( $auth_id ),
				'expires_at'    => gmdate( 'Y-m-d\TH:i:s\Z', $record['expires_at'] ),
			]
		);
	}

	/**
	 * Exchange an approved one-time code for WooCommerce REST API credentials.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function token( WP_REST_Request $request ) {
		$code  = (string) $request->get_param( 'code' );
		$state = (string) $request->get_param( 'state' );

		if ( '' === $code || '' === $state ) {
			return self::rest_error( 'missing_token_parameter', __( 'The code and state parameters are required.', 'woocommerce-payments' ) );
		}

		$records = self::get_records();
		foreach ( $records as $auth_id => $record ) {
			if ( empty( $record['code_hash'] ) || ! wp_check_password( $code, $record['code_hash'] ) ) {
				continue;
			}

			if ( ! hash_equals( (string) $record['state'], $state ) ) {
				return self::rest_error( 'state_mismatch', __( 'The authorization state does not match.', 'woocommerce-payments' ) );
			}

			if ( self::is_expired( $record ) ) {
				unset( $records[ $auth_id ] );
				self::save_records( $records );
				return self::rest_error( 'code_expired', __( 'The authorization code has expired.', 'woocommerce-payments' ) );
			}

			if ( ! empty( $record['used_at'] ) || 'approved' !== ( $record['status'] ?? '' ) ) {
				return self::rest_error( 'code_used', __( 'The authorization code has already been used.', 'woocommerce-payments' ) );
			}

			$credentials = self::create_api_key( $record );
			if ( is_wp_error( $credentials ) ) {
				return $credentials;
			}

			$record['used_at'] = time();
			$record['status']  = 'used';
			unset( $records[ $auth_id ] );
			self::save_records( $records );

			return rest_ensure_response( $credentials );
		}

		return self::rest_error( 'invalid_code', __( 'The authorization code is invalid.', 'woocommerce-payments' ) );
	}

	/**
	 * Redirect unauthenticated browser approval attempts through WordPress login.
	 */
	public static function redirect_admin_authorize_to_login(): void {
		$auth_id = isset( $_REQUEST['auth_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['auth_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		wp_safe_redirect( self::get_authorize_login_url( $auth_id ) );
		exit;
	}

	/**
	 * Handle browser approval or denial.
	 */
	public static function handle_admin_authorize(): void {
		self::cleanup_expired_records();

		$auth_id = isset( $_REQUEST['auth_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['auth_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$record  = self::get_record( $auth_id );

		if ( ! $record ) {
			wp_die( esc_html__( 'The WooPayments CLI authorization request is invalid or has expired.', 'woocommerce-payments' ), esc_html__( 'WooPayments CLI authorization', 'woocommerce-payments' ), [ 'response' => 400 ] );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to approve WooPayments CLI access.', 'woocommerce-payments' ), esc_html__( 'WooPayments CLI authorization', 'woocommerce-payments' ), [ 'response' => 403 ] );
		}

		if ( self::is_expired( $record ) ) {
			self::delete_record( $auth_id );
			self::redirect_error( $record, 'expired', __( 'The WooPayments CLI authorization request has expired.', 'woocommerce-payments' ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			check_admin_referer( 'wcpay_cli_authorize_' . $auth_id );
			$decision = isset( $_POST['wcpay_cli_decision'] ) ? sanitize_key( wp_unslash( $_POST['wcpay_cli_decision'] ) ) : '';

			if ( 'approve' !== $decision ) {
				self::delete_record( $auth_id );
				self::redirect_error( $record, 'access_denied', __( 'WooPayments CLI access was denied.', 'woocommerce-payments' ) );
			}

			$code                  = self::generate_random_token();
			$record['code_hash']   = wp_hash_password( $code );
			$record['approved_by'] = get_current_user_id();
			$record['status']      = 'approved';
			$record['expires_at']  = time() + self::TTL;
			self::save_record( $auth_id, $record );

			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Callback URL is validated to localhost before it is stored.
			wp_redirect(
				add_query_arg(
					[
						'success' => 1,
						'state'   => rawurlencode( (string) $record['state'] ),
						'code'    => rawurlencode( $code ),
					],
					$record['callback_url']
				)
			);
			exit;
		}

		self::render_approval_screen( $record );
	}

	/**
	 * Get the admin approval URL.
	 *
	 * @param string $auth_id Authorization ID.
	 * @return string
	 */
	private static function get_authorize_url( string $auth_id ): string {
		return add_query_arg(
			[
				'action'  => 'wcpay_cli_authorize',
				'auth_id' => rawurlencode( $auth_id ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Get the admin approval URL wrapped in the WordPress login flow.
	 *
	 * @param string $auth_id Authorization ID.
	 * @return string
	 */
	private static function get_authorize_login_url( string $auth_id ): string {
		return wp_login_url( self::get_authorize_url( $auth_id ) );
	}

	/**
	 * Render the approval form.
	 *
	 * @param array $record Authorization record.
	 */
	private static function render_approval_screen( array $record ): void {
		$auth_id       = (string) $record['id'];
		$callback_host = wp_parse_url( (string) $record['callback_url'], PHP_URL_HOST );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<meta name="robots" content="noindex, nofollow" />
			<title><?php esc_html_e( 'WooPayments CLI authorization', 'woocommerce-payments' ); ?></title>
			<?php wp_admin_css( 'install', true ); ?>
			<?php if ( function_exists( 'WC' ) && WC() ) : ?>
				<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Match WooCommerce auth template output. ?>
				<link rel="stylesheet" href="<?php echo esc_url( str_replace( [ 'http:', 'https:' ], '', WC()->plugin_url() ) . '/assets/css/auth.css' ); ?>" type="text/css" />
			<?php endif; ?>
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone admin-post auth page. ?>
			<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/css/wcpay-cli-auth.css', WCPAY_PLUGIN_FILE ) ); ?>" type="text/css" />
		</head>
		<body class="wc-auth wp-core-ui">
			<div class="wc-auth-content">
				<div id="wc-logo">
					<img src="<?php echo esc_url( plugins_url( 'assets/images/woopayments.svg', WCPAY_PLUGIN_FILE ) ); ?>" alt="<?php esc_attr_e( 'WooPayments', 'woocommerce-payments' ); ?>" />
				</div>
				<h1>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: application name. */
							__( '%s would like to connect to your store', 'woocommerce-payments' ),
							$record['app_name']
						)
					);
					?>
				</h1>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: application name, 2: scope. */
							__( 'This will create WooCommerce REST API keys for <strong>%1$s</strong> with <strong>%2$s</strong> access.', 'woocommerce-payments' ),
							esc_html( $record['app_name'] ),
							esc_html( $record['scope'] )
						)
					);
					?>
				</p>
				<ul class="wc-auth-permissions">
					<li><?php esc_html_e( 'View and manage WooCommerce data through the WooPayments CLI.', 'woocommerce-payments' ); ?></li>
					<li><?php esc_html_e( 'Create the API keys for your administrator account.', 'woocommerce-payments' ); ?></li>
				</ul>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: callback URL host. */
							__( 'Approving will share a one-time authorization code with <strong>%s</strong>. Do not proceed if this looks suspicious.', 'woocommerce-payments' ),
							esc_html( $callback_host ? $callback_host : $record['callback_url'] )
						)
					);
					?>
				</p>
				<div class="wcpay-cli-auth__detail">
					<p><?php esc_html_e( 'The consumer secret will only be returned to the CLI token exchange and will not appear in the browser URL.', 'woocommerce-payments' ); ?></p>
				</div>
				<div class="wc-auth-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wcpay_cli_authorize" />
						<input type="hidden" name="auth_id" value="<?php echo esc_attr( $auth_id ); ?>" />
						<?php wp_nonce_field( 'wcpay_cli_authorize_' . $auth_id ); ?>
						<button type="submit" class="button button-primary wc-auth-approve" name="wcpay_cli_decision" value="approve"><?php esc_html_e( 'Approve', 'woocommerce-payments' ); ?></button>
						<button type="submit" class="button wc-auth-deny wcpay-cli-auth__deny" name="wcpay_cli_decision" value="deny"><?php esc_html_e( 'Deny', 'woocommerce-payments' ); ?></button>
					</form>
				</div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Validate authorize request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private static function validate_authorize_request( WP_REST_Request $request ) {
		$app_name     = (string) $request->get_param( 'app_name' );
		$scope        = (string) $request->get_param( 'scope' );
		$state        = (string) $request->get_param( 'state' );
		$callback_url = (string) $request->get_param( 'callback_url' );

		if ( '' === trim( $app_name ) ) {
			return self::rest_error( 'missing_app_name', __( 'The app_name parameter is required.', 'woocommerce-payments' ) );
		}

		if ( ! in_array( $scope, [ 'read', 'write', 'read_write' ], true ) ) {
			return self::rest_error( 'invalid_scope', __( 'The scope parameter must be read, write, or read_write.', 'woocommerce-payments' ) );
		}

		if ( strlen( $state ) < 16 ) {
			return self::rest_error( 'invalid_state', __( 'The state parameter must be at least 16 characters.', 'woocommerce-payments' ) );
		}

		if ( ! self::is_localhost_callback_url( $callback_url ) ) {
			return self::rest_error( 'invalid_callback_url', __( 'The callback_url must be an HTTP localhost URL with an explicit port.', 'woocommerce-payments' ) );
		}

		return true;
	}

	/**
	 * Check whether a callback URL is an allowed localhost URL.
	 *
	 * @param string $url Callback URL.
	 * @return bool
	 */
	private static function is_localhost_callback_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( trim( (string) ( $parts['host'] ?? '' ), '[]' ) );
		$port   = $parts['port'] ?? 0;

		return 'http' === $scheme
			&& in_array( $host, [ '127.0.0.1', 'localhost', '::1' ], true )
			&& is_int( $port )
			&& $port > 0
			&& $port <= 65535;
	}

	/**
	 * Create a WooCommerce REST API key for the approving user.
	 *
	 * @param array $record Authorization record.
	 * @return array|WP_Error
	 */
	private static function create_api_key( array $record ) {
		global $wpdb;

		$user_id = absint( $record['approved_by'] ?? 0 );
		if ( ! $user_id ) {
			return self::rest_error( 'missing_approver', __( 'The authorization has no approving user.', 'woocommerce-payments' ) );
		}

		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();
		$description     = sprintf(
			'WooPayments CLI - API (%s)',
			gmdate( 'Y-m-d H:i:s' )
		);

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			[
				'user_id'         => $user_id,
				'description'     => $description,
				'permissions'     => sanitize_key( $record['scope'] ),
				'consumer_key'    => wc_api_hash( $consumer_key ),
				'consumer_secret' => $consumer_secret,
				'truncated_key'   => substr( $consumer_key, -7 ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			return self::rest_error( 'key_creation_failed', __( 'Unable to create WooCommerce REST API credentials.', 'woocommerce-payments' ), 500 );
		}

		return [
			'consumer_key'    => $consumer_key,
			'consumer_secret' => $consumer_secret,
			'key_id'          => (string) $wpdb->insert_id,
		];
	}

	/**
	 * Redirect an error to the local CLI callback.
	 *
	 * @param array  $record Authorization record.
	 * @param string $code Error code.
	 * @param string $description Human-readable description.
	 */
	private static function redirect_error( array $record, string $code, string $description ): void {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Callback URL is validated to localhost before it is stored.
		wp_redirect(
			add_query_arg(
				[
					'success'           => 0,
					'state'             => rawurlencode( (string) $record['state'] ),
					'error'             => rawurlencode( $code ),
					'error_description' => rawurlencode( $description ),
				],
				$record['callback_url']
			)
		);
		exit;
	}

	/**
	 * Return a REST error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @return WP_Error
	 */
	private static function rest_error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( 'wcpay_cli_' . $code, $message, [ 'status' => $status ] );
	}

	/**
	 * Generate a high-entropy URL-safe token.
	 *
	 * @return string
	 */
	private static function generate_random_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Check whether a record has expired.
	 *
	 * @param array $record Authorization record.
	 * @return bool
	 */
	private static function is_expired( array $record ): bool {
		return empty( $record['expires_at'] ) || time() > (int) $record['expires_at'];
	}

	/**
	 * Delete expired records.
	 */
	private static function cleanup_expired_records(): void {
		$records = self::get_records();
		foreach ( $records as $auth_id => $record ) {
			if ( self::is_expired( $record ) ) {
				unset( $records[ $auth_id ] );
			}
		}
		self::save_records( $records );
	}

	/**
	 * Get all records.
	 *
	 * @return array
	 */
	private static function get_records(): array {
		$records = get_option( self::OPTION_NAME, [] );
		return is_array( $records ) ? $records : [];
	}

	/**
	 * Save all records.
	 *
	 * @param array $records Records.
	 */
	private static function save_records( array $records ): void {
		update_option( self::OPTION_NAME, $records, false );
	}

	/**
	 * Get a single record.
	 *
	 * @param string $auth_id Authorization ID.
	 * @return array|null
	 */
	private static function get_record( string $auth_id ): ?array {
		$records = self::get_records();
		return isset( $records[ $auth_id ] ) && is_array( $records[ $auth_id ] ) ? $records[ $auth_id ] : null;
	}

	/**
	 * Save a single record.
	 *
	 * @param string $auth_id Authorization ID.
	 * @param array  $record Record.
	 */
	private static function save_record( string $auth_id, array $record ): void {
		$records             = self::get_records();
		$records[ $auth_id ] = $record;
		self::save_records( $records );
	}

	/**
	 * Delete a single record.
	 *
	 * @param string $auth_id Authorization ID.
	 */
	private static function delete_record( string $auth_id ): void {
		$records = self::get_records();
		unset( $records[ $auth_id ] );
		self::save_records( $records );
	}
}
