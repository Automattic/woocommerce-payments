<?php
/**
 * WC_Payments_Token_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;
use WCPay\Payment_Methods\CC_Payment_Gateway;
use WCPay\Constants\Payment_Method;

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 */
class WC_Payments_Token_Service {
	const REUSABLE_GATEWAYS_BY_PAYMENT_METHOD = [
		Payment_Method::CARD => WC_Payment_Gateway_WCPay::GATEWAY_ID,
		Payment_Method::SEPA => WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . Payment_Method::SEPA,
		Payment_Method::LINK => WC_Payment_Gateway_WCPay::GATEWAY_ID,
	];

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_Token_Service constructor.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client Payments API client.
	 * @param WC_Payments_Customer_Service $customer_service Customer class instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Customer_Service $customer_service ) {
		$this->payments_api_client = $payments_api_client;
		$this->customer_service    = $customer_service;

		add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );
		add_action( 'woocommerce_payment_token_set_default', [ $this, 'woocommerce_payment_token_set_default' ], 10, 2 );
		add_filter( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );
		add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'get_account_saved_payment_methods_list_item_sepa' ], 10, 2 );
		add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'get_account_saved_payment_methods_list_item_link' ], 10, 2 );
		add_filter( 'woocommerce_get_credit_card_type_label', [ $this, 'normalize_sepa_label' ] );
		add_filter( 'woocommerce_get_credit_card_type_label', [ $this, 'normalize_stripe_link_label' ] );
	}

	/**
	 * Creates and add a token to an user, based on the payment_method object
	 *
	 * @param   array   $payment_method                                          Payment method to be added.
	 * @param   WP_User $user                                                    User to attach payment method to.
	 * @return  WC_Payment_Token|WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA The WC object for the payment token.
	 */
	public function add_token_to_user( $payment_method, $user ) {
		// Clear cached payment methods.
		$this->customer_service->clear_cached_payment_methods_for_user( $user->ID );

		switch ( $payment_method['type'] ) {
			case Payment_Method::SEPA:
				$token      = new WC_Payment_Token_WCPay_SEPA();
				$gateway_id = WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . Payment_Method::SEPA;
				$token->set_gateway_id( $gateway_id );
				$token->set_last4( $payment_method[ Payment_Method::SEPA ]['last4'] );
				break;
			case Payment_Method::LINK:
				$token      = new WC_Payment_Token_WCPay_Link();
				$gateway_id = CC_Payment_Gateway::GATEWAY_ID;
				$token->set_gateway_id( $gateway_id );
				$token->set_email( $payment_method[ Payment_Method::LINK ]['email'] );
				break;
			case Payment_Method::CARD_PRESENT:
				$token = new WC_Payment_Token_CC();
				$token->set_gateway_id( CC_Payment_Gateway::GATEWAY_ID );
				$token->set_expiry_month( $payment_method[ Payment_Method::CARD_PRESENT ]['exp_month'] );
				$token->set_expiry_year( $payment_method[ Payment_Method::CARD_PRESENT ]['exp_year'] );
				$token->set_card_type( strtolower( $payment_method[ Payment_Method::CARD_PRESENT ]['brand'] ) );
				$token->set_last4( $payment_method[ Payment_Method::CARD_PRESENT ]['last4'] );
				break;
			default:
				$token = new WC_Payment_Token_CC();
				$token->set_gateway_id( CC_Payment_Gateway::GATEWAY_ID );
				$token->set_expiry_month( $payment_method[ Payment_Method::CARD ]['exp_month'] );
				$token->set_expiry_year( $payment_method[ Payment_Method::CARD ]['exp_year'] );
				$token->set_card_type( strtolower( $payment_method[ Payment_Method::CARD ]['display_brand'] ?? $payment_method[ Payment_Method::CARD ]['networks']['preferred'] ?? $payment_method[ Payment_Method::CARD ]['brand'] ) );
				$token->set_last4( $payment_method[ Payment_Method::CARD ]['last4'] );

		}
		$token->set_token( $payment_method['id'] );
		$token->set_user_id( $user->ID );
		$token->save();

		return $token;
	}

	/**
	 * Adds a payment method to a user.
	 *
	 * @param string  $payment_method_id Payment method to be added.
	 * @param WP_User $user              User to attach payment method to.
	 * @return WC_Payment_Token_CC       The newly created token.
	 */
	public function add_payment_method_to_user( $payment_method_id, $user ) {
		$payment_method_object = $this->payments_api_client->get_payment_method( $payment_method_id );
		return $this->add_token_to_user( $payment_method_object, $user );
	}

	/**
	 * Returns boolean value if payment method type matches relevant payment gateway.
	 *
	 * @param string $payment_method_type Stripe payment method type ID.
	 * @param string $gateway_id          WC payment gateway ID.
	 * @return bool                       True, if payment method type matches gateway, false if otherwise.
	 */
	public function is_valid_payment_method_type_for_gateway( $payment_method_type, $gateway_id ) {
		return self::REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_type ] === $gateway_id;
	}

	/**
	 * Gets saved tokens from API if they don't already exist in WooCommerce.
	 *
	 * @param array  $tokens     Array of tokens.
	 * @param string $user_id    WC user ID.
	 * @param string $gateway_id WC gateway ID.
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens( $tokens, $user_id, $gateway_id ) {

		if ( ( ! empty( $gateway_id ) && ! in_array( $gateway_id, self::REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) || ! is_user_logged_in() ) {
			return $tokens;
		}

		if ( count( $tokens ) >= get_option( 'posts_per_page' ) ) {
			// The tokens data store is not paginated and only the first "post_per_page" (defaults to 10) tokens are retrieved.
			// Having 10 saved credit cards is considered an unsupported edge case, new ones that have been stored in Stripe won't be added.
			return $tokens;
		}

		try {
			$customer_id = $this->customer_service->get_customer_id_by_user_id( $user_id );

			if ( null === $customer_id ) {
				return $tokens;
			}

			$stored_tokens = [];

			foreach ( $tokens as $token ) {
				if ( in_array( $token->get_gateway_id(), self::REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) {
					$stored_tokens[ $token->get_token() ] = $token;
				}
			}

			$retrievable_payment_method_types = $this->get_retrievable_payment_method_types( $gateway_id );

			$payment_methods = [];

			foreach ( $retrievable_payment_method_types as $type ) {
				$payment_methods[] = $this->customer_service->get_payment_methods_for_customer( $customer_id, $type );
			}

			$payment_methods = array_merge( ...$payment_methods );

		} catch ( Exception $e ) {
			Logger::error( 'Failed to fetch payment methods for customer.' . $e );
			return $tokens;
		}

		// Prevent unnecessary recursion, WC_Payment_Token::save() ends up calling 'woocommerce_get_customer_payment_tokens' in some cases.
		remove_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );

		foreach ( $payment_methods as $payment_method ) {
			if ( ! isset( $payment_method['type'] ) ) {
				continue;
			}
			if ( ! isset( $stored_tokens[ $payment_method['id'] ] ) && ( $this->is_valid_payment_method_type_for_gateway( $payment_method['type'], $gateway_id ) || empty( $gateway_id ) ) ) {
				$token                      = $this->add_token_to_user( $payment_method, get_user_by( 'id', $user_id ) );
				$tokens[ $token->get_id() ] = $token;
			} else {
				unset( $stored_tokens[ $payment_method['id'] ] );
			}
		}
		add_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );

		// Remove the payment methods that no longer exist in Stripe's side.
		remove_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );
		foreach ( $stored_tokens as $token ) {
			unset( $tokens[ $token->get_id() ] );
			$token->delete();
		}
		add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );

		return $tokens;
	}

	/**
	 * Retrieves the payment method types for which tokens should be retrieved.
	 *
	 * This function determines the appropriate payment method types based on the provided gateway ID.
	 * - If a gateway ID is provided, it retrieves the payment methods specific to that gateway to prevent duplication of saved tokens under incorrect payment methods during checkout.
	 * - If no gateway ID is provided, it retrieves the default payment methods to fetch all saved tokens, e.g., for the Blocks checkout or My Account page.
	 *
	 * @param string|null $gateway_id The optional ID of the gateway.
	 * @return array The list of retrievable payment method types.
	 */
	private function get_retrievable_payment_method_types( $gateway_id = null ) {
		if ( empty( $gateway_id ) ) {
			return $this->get_all_retrievable_payment_types();
		} else {
			return $this->get_gateway_specific_retrievable_payment_types( $gateway_id );
		}
	}

	/**
	 * Returns all the enabled retrievable payment method types.
	 *
	 * @return array Enabled retrievable payment method types.
	 */
	private function get_all_retrievable_payment_types() {
		$types = [ Payment_Method::CARD ];

		if ( $this->is_payment_method_enabled( Payment_Method::SEPA ) ) {
			$types[] = Payment_Method::SEPA;
		}

		if ( $this->is_payment_method_enabled( Payment_Method::LINK ) ) {
			$types[] = Payment_Method::LINK;
		}

		return $types;
	}
	/**
	 * Returns retrievable payment method types for a given gateway.
	 *
	 * @param string $gateway_id The ID of the gateway.
	 * @return array Retrievable payment method types for the specified gateway.
	 */
	private function get_gateway_specific_retrievable_payment_types( $gateway_id ) {
		$types = [];

		foreach ( self::REUSABLE_GATEWAYS_BY_PAYMENT_METHOD as $payment_method => $gateway ) {
			if ( $gateway !== $gateway_id ) {
				continue;
			}

			// Stripe Link is part of the card gateway, so we need to check separately if Link is enabled.
			if ( Payment_Method::LINK === $payment_method && ! $this->is_payment_method_enabled( Payment_Method::LINK ) ) {
				continue;
			}

			$types[] = $payment_method;
		}

		return $types;
	}

	/**
	 * Checks if a payment method is enabled.
	 *
	 * @param string $payment_method The payment method to check.
	 * @return bool True if the payment method is enabled, false otherwise.
	 */
	private function is_payment_method_enabled( $payment_method ) {
		return in_array( $payment_method, WC_Payments::get_gateway()->get_upe_enabled_payment_method_ids(), true );
	}

	/**
	 * Delete token from Stripe.
	 *
	 * @param string           $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 */
	public function woocommerce_payment_token_deleted( $token_id, $token ) {

		if ( in_array( $token->get_gateway_id(), self::REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) {
			try {
				$this->payments_api_client->detach_payment_method( $token->get_token() );
				// Clear cached payment methods.
				$this->customer_service->clear_cached_payment_methods_for_user( $token->get_user_id() );
			} catch ( Exception $e ) {
				Logger::log( 'Error detaching payment method:' . $e->getMessage() );
			}
		}
	}

	/**
	 * Set as default in Stripe.
	 *
	 * @param string           $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 */
	public function woocommerce_payment_token_set_default( $token_id, $token ) {

		if ( in_array( $token->get_gateway_id(), self::REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) {
			$customer_id = $this->customer_service->get_customer_id_by_user_id( $token->get_user_id() );
			if ( $customer_id ) {
				$this->customer_service->set_default_payment_method_for_customer( $customer_id, $token->get_token() );
				// Clear cached payment methods.
				$this->customer_service->clear_cached_payment_methods_for_user( $token->get_user_id() );
			}
		}
	}

	/**
	 * Controls the output for SEPA on the my account page.
	 *
	 * @param  array                                        $item          Individual list item from woocommerce_saved_payment_methods_list.
	 * @param  WC_Payment_Token|WC_Payment_Token_WCPay_SEPA $payment_token The payment token associated with this method entry.
	 * @return array                                        Filtered item
	 */
	public function get_account_saved_payment_methods_list_item_sepa( $item, $payment_token ) {
		if ( WC_Payment_Token_WCPay_SEPA::TYPE === strtolower( $payment_token->get_type() ) ) {
			$item['method']['last4'] = $payment_token->get_last4();
			$item['method']['brand'] = esc_html__( 'SEPA IBAN', 'woocommerce-payments' );
		}

		return $item;
	}

	/**
	 * Controls the output for Stripe Link on the My account page.
	 *
	 * @param  array                                        $item          Individual list item from woocommerce_saved_payment_methods_list.
	 * @param  WC_Payment_Token|WC_Payment_Token_WCPay_Link $payment_token The payment token associated with this method entry.
	 * @return array                                        Filtered item
	 */
	public function get_account_saved_payment_methods_list_item_link( $item, $payment_token ) {
		if ( WC_Payment_Token_WCPay_Link::TYPE === strtolower( $payment_token->get_type() ) ) {
			$item['method']['last4'] = $payment_token->get_redacted_email();
			$item['method']['brand'] = esc_html__( 'Stripe Link email', 'woocommerce-payments' );
		}
		return $item;
	}

	/**
	 * Normalizes the SEPA IBAN label on My Account page.
	 *
	 * @param string $label Token label.
	 * @return string $label Capitalized SEPA IBAN label.
	 */
	public function normalize_sepa_label( $label ) {
		if ( 'sepa iban' === strtolower( $label ) ) {
			return __( 'SEPA IBAN', 'woocommerce-payments' );
		}

		return $label;
	}

	/**
	 * Normalizes the Stripe Link label on My Account page.
	 *
	 * @param string $label Token label.
	 * @return string $label Capitalized SEPA IBAN label.
	 */
	public function normalize_stripe_link_label( $label ) {
		if ( 'stripe link email' === strtolower( $label ) ) {
			return 'Stripe Link email';
		}

		return $label;
	}
}
