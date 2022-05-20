<?php
/**
 * WC_Payments_Payment_Tokens class
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

		if ( Payment_Method::SEPA === $payment_method['type'] ) {
			$token = new WC_Payment_Token_WCPay_SEPA();
			$token->set_gateway_id( CC_Payment_Gateway::GATEWAY_ID );
			$token->set_last4( $payment_method[ Payment_Method::SEPA ]['last4'] );
		} else {
			$token = new WC_Payment_Token_CC();
			$token->set_gateway_id( CC_Payment_Gateway::GATEWAY_ID );
			$token->set_expiry_month( $payment_method[ Payment_Method::CARD ]['exp_month'] );
			$token->set_expiry_year( $payment_method[ Payment_Method::CARD ]['exp_year'] );
			$token->set_card_type( strtolower( $payment_method[ Payment_Method::CARD ]['brand'] ) );
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
	 * Gets saved tokens from API if they don't already exist in WooCommerce.
	 *
	 * @param array  $tokens     Array of tokens.
	 * @param string $user_id    WC user ID.
	 * @param string $gateway_id WC gateway ID.
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens( $tokens, $user_id, $gateway_id ) {
		if ( ( ! empty( $gateway_id ) && WC_Payment_Gateway_WCPay::GATEWAY_ID !== $gateway_id ) || ! is_user_logged_in() ) {
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
				if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $token->get_gateway_id() ) {
					$stored_tokens[ $token->get_token() ] = $token;
				}
			}

			$payment_methods = [ [] ];
			foreach ( WC_Payments::get_gateway()->get_upe_enabled_payment_method_ids() as $type ) {
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

			if ( ! isset( $stored_tokens[ $payment_method['id'] ] ) ) {
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
	 * Delete token from Stripe.
	 *
	 * @param string           $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 */
	public function woocommerce_payment_token_deleted( $token_id, $token ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $token->get_gateway_id() ) {
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
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $token->get_gateway_id() ) {
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
}
