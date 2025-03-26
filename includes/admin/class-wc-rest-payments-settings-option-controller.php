<?php
/**
 * Class WC_REST_Payments_Settings_Option_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings options.
 */
class WC_REST_Payments_Settings_Option_Controller extends WC_Payments_REST_Controller {

	/**
	 * List of allowed option keys that can be updated via the REST API.
	 *
	 * @var array
	 */
	private const ALLOWED_OPTIONS = [
		'wcpay_frt_discover_banner_settings',
		'wcpay_multi_currency_setup_completed',
		'woocommerce_dismissed_todo_tasks',
		'woocommerce_remind_me_later_todo_tasks',
		'woocommerce_deleted_todo_tasks',
		'wcpay_fraud_protection_welcome_tour_dismissed',
		'wcpay_capability_request_dismissed_notices',
		'wcpay_onboarding_eligibility_modal_dismissed',
		'wcpay_connection_success_modal_dismissed',
		'wcpay_next_deposit_notice_dismissed',
		'wcpay_duplicate_payment_method_notices_dismissed',
		'wcpay_exit_survey_dismissed',
		'wcpay_instant_deposit_notice_dismissed',
		'wcpay_date_format_notice_dismissed',
	];

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/settings';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<option_key>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_option' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'option_key' => [
						'required'          => true,
						'validate_callback' => [ $this, 'validate_option_key' ],
					],
					'value'      => [
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Verify access.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Validate the option key.
	 *
	 * @param string $option_key The option key to validate.
	 * @return bool
	 */
	public function validate_option_key( string $option_key ): bool {
		return in_array( $option_key, self::ALLOWED_OPTIONS, true );
	}

	/**
	 * Update the option value.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function update_option( WP_REST_Request $request ) {
		$option_key = $request->get_param( 'option_key' );
		$value      = $request->get_param( 'value' );

		update_option( $option_key, $value );

		return rest_ensure_response(
			[
				'success' => true,
			]
		);
	}
}
