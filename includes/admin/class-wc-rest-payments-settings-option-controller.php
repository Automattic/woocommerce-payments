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
	 * Map of allowed option names to their accepted value types.
	 * Types: 'bool', 'array', 'string'.
	 *
	 * @var array
	 */
	private const ALLOWED_OPTIONS = [
		'wcpay_multi_currency_setup_completed'             => 'bool',
		'woocommerce_dismissed_todo_tasks'                 => 'array',
		'woocommerce_remind_me_later_todo_tasks'           => 'array',
		'woocommerce_deleted_todo_tasks'                   => 'array',
		'wcpay_fraud_protection_welcome_tour_dismissed'    => 'bool',
		'wcpay_onboarding_eligibility_modal_dismissed'     => 'bool',
		'wcpay_connection_success_modal_dismissed'         => 'bool',
		'wcpay_next_deposit_notice_dismissed'              => 'bool',
		'wcpay_duplicate_payment_method_notices_dismissed' => 'bool',
		'wcpay_instant_deposit_notice_dismissed'           => 'bool',
		'wcpay_exit_survey_last_shown'                     => 'string',
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
			'/' . $this->rest_base . '/(?P<option_name>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_option' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'option_name' => [
						'required'          => true,
						'validate_callback' => [ $this, 'validate_option_name' ],
					],
					'value'       => [
						'required'          => true,
						'validate_callback' => [ $this, 'validate_value' ],
					],
				],
			]
		);
	}

	/**
	 * Validate the option name.
	 *
	 * @param string $option_name The option name to validate.
	 * @return bool
	 */
	public function validate_option_name( string $option_name ): bool {
		return array_key_exists( $option_name, self::ALLOWED_OPTIONS );
	}

	/**
	 * Validate the value parameter based on the option's expected type.
	 *
	 * @param mixed           $value   The value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_value( $value, WP_REST_Request $request ) {
		$option_name   = $request->get_param( 'option_name' );
		$expected_type = self::ALLOWED_OPTIONS[ $option_name ] ?? null;

		$is_valid = false;
		switch ( $expected_type ) {
			case 'bool':
				$is_valid = is_bool( $value );
				break;
			case 'array':
				$is_valid = is_array( $value );
				break;
			case 'string':
				$is_valid = is_string( $value );
				break;
		}

		if ( $is_valid ) {
			return true;
		}

		return new WP_Error(
			'rest_invalid_param',
			/* translators: %s: expected type (bool, array, or string) */
			sprintf( __( 'Invalid value type; expected %s', 'woocommerce-payments' ), $expected_type ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Update the option value.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_option( WP_REST_Request $request ) {
		$option_name = $request->get_param( 'option_name' );
		$value       = $request->get_param( 'value' );

		update_option( $option_name, $value );

		return rest_ensure_response(
			[
				'success' => true,
			]
		);
	}
}
