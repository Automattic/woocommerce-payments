<?php
/**
 * Class WC_Payments_Express_Checkout_Custom_Fields_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * Adds classic checkout custom field support to Express Checkout Store API orders.
 */
class WC_Payments_Express_Checkout_Custom_Fields_Handler {
	const EXTENSION_NAMESPACE   = 'woocommerce-payments/express-checkout';
	const CUSTOM_CHECKOUT_DATA  = 'custom_checkout_data';
	const CHECKOUT_ENDPOINT     = 'checkout';
	const CHECKOUT_FIELD_GROUPS = [ 'billing', 'shipping', 'order' ];

	const STANDARD_CHECKOUT_FIELDS = [
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_country',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_phone',
		'billing_email',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_country',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		'shipping_phone',
		'order_comments',
	];

	/**
	 * Tracks whether the Store API extension schema was registered.
	 *
	 * @var bool
	 */
	private $store_api_extension_registered = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_store_api_extension' ] );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'process_store_api_checkout_request' ], 10, 2 );

		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			$this->register_store_api_extension();
		}
	}

	/**
	 * Register the Store API extension schema used by express checkout custom fields.
	 *
	 * @return void
	 */
	public function register_store_api_extension() {
		if ( $this->store_api_extension_registered || ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		$result = woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => self::CHECKOUT_ENDPOINT,
				'namespace'       => self::EXTENSION_NAMESPACE,
				'schema_callback' => [ $this, 'get_store_api_extension_schema' ],
				'data_callback'   => [ $this, 'get_store_api_extension_data' ],
				'schema_type'     => 'ARRAY_A',
			]
		);

		if ( is_wp_error( $result ) ) {
			return;
		}

		$this->store_api_extension_registered = true;
	}

	/**
	 * Returns the Store API extension schema.
	 *
	 * @return array
	 */
	public function get_store_api_extension_schema() {
		return [
			self::CUSTOM_CHECKOUT_DATA => [
				'description' => __( 'Serialized custom checkout field data for WooPayments Express Checkout.', 'woocommerce-payments' ),
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
			],
		];
	}

	/**
	 * Returns data for checkout responses.
	 *
	 * @return array
	 */
	public function get_store_api_extension_data() {
		return [];
	}

	/**
	 * Process custom checkout fields sent with an Express Checkout Store API checkout request.
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @throws RouteException When custom checkout field validation fails.
	 *
	 * @return void
	 */
	public function process_store_api_checkout_request( WC_Order $order, WP_REST_Request $request ) {
		$custom_checkout_data = $this->get_custom_checkout_data_from_request( $request );

		if ( [] === $custom_checkout_data ) {
			// Required custom checkout field validation depends on Express Checkout sending its custom-field payload.
			return;
		}

		$custom_checkout_fields = self::get_custom_checkout_fields();
		$custom_checkout_data   = $this->sanitize_custom_checkout_data( $custom_checkout_data, $custom_checkout_fields );
		$errors                 = new WP_Error();

		$this->validate_required_custom_checkout_fields( $custom_checkout_data, $errors, $custom_checkout_fields );

		/**
		 * Allows extensions to validate Express Checkout custom checkout fields.
		 *
		 * @since 10.9.0
		 *
		 * @param array           $custom_checkout_data Custom checkout data.
		 * @param WP_Error        $errors Validation errors.
		 * @param WC_Order        $order Order object.
		 * @param WP_REST_Request $request Full request object.
		 */
		do_action( 'wcpay_express_checkout_after_custom_fields_validation', $custom_checkout_data, $errors, $order, $request );

		if ( $errors->has_errors() ) {
			throw new RouteException(
				'woocommerce_payments_express_checkout_custom_fields_validation_error',
				implode( ' ', $errors->get_error_messages() ),
				400
			);
		}

		$this->save_registered_custom_checkout_fields( $order, $custom_checkout_data, $custom_checkout_fields );

		/**
		 * Allows extensions to save Express Checkout custom checkout fields to the order.
		 *
		 * @since 10.9.0
		 *
		 * @param int             $order_id Order ID.
		 * @param array           $custom_checkout_data Custom checkout data.
		 * @param WC_Order        $order Order object.
		 * @param WP_REST_Request $request Full request object.
		 */
		do_action( 'wcpay_express_checkout_update_custom_fields_order_meta', $order->get_id(), $custom_checkout_data, $order, $request );
	}

	/**
	 * Gets classic checkout custom field definitions.
	 *
	 * @return array
	 */
	public static function get_custom_checkout_fields(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->checkout() ) {
			return [];
		}

		$checkout_fields = WC()->checkout()->get_checkout_fields();
		$custom_fields   = [];

		foreach ( self::CHECKOUT_FIELD_GROUPS as $field_group ) {
			if ( empty( $checkout_fields[ $field_group ] ) || ! is_array( $checkout_fields[ $field_group ] ) ) {
				continue;
			}

			foreach ( $checkout_fields[ $field_group ] as $field_name => $field ) {
				if ( in_array( $field_name, self::STANDARD_CHECKOUT_FIELDS, true ) ) {
					continue;
				}

				$custom_fields[ $field_name ] = [
					'type'     => $field['type'] ?? 'text',
					'label'    => $field['label'] ?? $field_name,
					'required' => ! empty( $field['required'] ),
					'location' => $field_group,
				];
			}
		}

		return $custom_fields;
	}

	/**
	 * Gets custom checkout data from a Store API request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @throws RouteException When custom checkout data is invalid.
	 *
	 * @return array
	 */
	private function get_custom_checkout_data_from_request( WP_REST_Request $request ): array {
		$extensions = $request->get_param( 'extensions' );

		if ( ! is_array( $extensions ) || ! isset( $extensions[ self::EXTENSION_NAMESPACE ][ self::CUSTOM_CHECKOUT_DATA ] ) ) {
			return [];
		}

		$custom_checkout_data = $extensions[ self::EXTENSION_NAMESPACE ][ self::CUSTOM_CHECKOUT_DATA ];

		if ( ! is_string( $custom_checkout_data ) ) {
			return [];
		}

		if ( '' === trim( $custom_checkout_data ) ) {
			return [];
		}

		$decoded_custom_checkout_data = json_decode( $custom_checkout_data, true );

		if ( ! is_array( $decoded_custom_checkout_data ) ) {
			throw new RouteException(
				'woocommerce_payments_express_checkout_invalid_custom_checkout_data',
				__( 'Invalid custom checkout field data.', 'woocommerce-payments' ),
				400
			);
		}

		return $decoded_custom_checkout_data;
	}

	/**
	 * Sanitizes custom checkout data according to the checkout field type.
	 *
	 * @param array $custom_checkout_data Custom checkout data.
	 * @param array $custom_checkout_fields Custom checkout field definitions.
	 * @return array
	 */
	private function sanitize_custom_checkout_data( array $custom_checkout_data, array $custom_checkout_fields ): array {
		$sanitized_data = [];

		foreach ( $custom_checkout_data as $field_name => $field_value ) {
			$field_name = wc_clean( wp_unslash( $field_name ) );

			if ( '' === $field_name ) {
				continue;
			}

			$field_type = $custom_checkout_fields[ $field_name ]['type'] ?? 'textarea';

			$sanitized_data[ $field_name ] = $this->sanitize_custom_checkout_field_value( $field_value, $field_type );
		}

		return $sanitized_data;
	}

	/**
	 * Sanitizes a single custom checkout field value.
	 *
	 * @param mixed  $field_value Field value.
	 * @param string $field_type Field type.
	 * @return mixed
	 */
	private function sanitize_custom_checkout_field_value( $field_value, string $field_type ) {
		if ( is_array( $field_value ) ) {
			return array_map(
				function ( $value ) use ( $field_type ) {
					return $this->sanitize_custom_checkout_field_value( $value, $field_type );
				},
				$field_value
			);
		}

		if ( 'textarea' === $field_type ) {
			return sanitize_textarea_field( wp_unslash( $field_value ) );
		}

		return sanitize_text_field( wp_unslash( $field_value ) );
	}

	/**
	 * Validates required custom checkout fields.
	 *
	 * @param array    $custom_checkout_data Custom checkout data.
	 * @param WP_Error $errors Validation errors.
	 * @param array    $custom_checkout_fields Custom checkout field definitions.
	 * @return void
	 */
	private function validate_required_custom_checkout_fields( array $custom_checkout_data, WP_Error $errors, array $custom_checkout_fields ) {
		foreach ( $custom_checkout_fields as $field_name => $field ) {
			if ( empty( $field['required'] ) ) {
				continue;
			}

			if ( ! array_key_exists( $field_name, $custom_checkout_data ) || $this->is_empty_custom_checkout_field_value( $custom_checkout_data[ $field_name ] ) ) {
				$errors->add(
					'wcpay_express_checkout_required_custom_field_' . sanitize_key( $field_name ),
					sprintf(
						/* translators: %s: checkout field label. */
						__( '%s is a required field.', 'woocommerce-payments' ),
						wp_strip_all_tags( $field['label'] ?? $field_name )
					)
				);
			}
		}
	}

	/**
	 * Saves WooCommerce-registered custom checkout fields to order meta.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $custom_checkout_data Custom checkout data.
	 * @param array    $custom_checkout_fields Custom checkout field definitions.
	 * @return void
	 */
	private function save_registered_custom_checkout_fields( WC_Order $order, array $custom_checkout_data, array $custom_checkout_fields ) {
		$updated_order_meta = false;

		foreach ( array_keys( $custom_checkout_fields ) as $field_name ) {
			if ( ! array_key_exists( $field_name, $custom_checkout_data ) ) {
				continue;
			}

			$order->update_meta_data( $field_name, $custom_checkout_data[ $field_name ] );
			$updated_order_meta = true;
		}

		if ( $updated_order_meta ) {
			$order->save_meta_data();
		}
	}

	/**
	 * Checks whether a custom checkout field value is empty.
	 *
	 * @param mixed $field_value Field value.
	 * @return bool
	 */
	private function is_empty_custom_checkout_field_value( $field_value ): bool {
		if ( is_array( $field_value ) ) {
			foreach ( $field_value as $value ) {
				if ( ! $this->is_empty_custom_checkout_field_value( $value ) ) {
					return false;
				}
			}

			return true;
		}

		return '' === trim( (string) $field_value );
	}
}
