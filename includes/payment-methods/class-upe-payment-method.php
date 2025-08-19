<?php
/**
 * UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Utils;
use WP_User;
use WC_Payments_Token_Service;
use WC_Payment_Token_CC;
use WC_Payment_Token_WCPay_SEPA;
use WC_Payments_Subscriptions_Utilities;

/**
 * Extendable class for payment methods.
 *
 * @template T of \WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface
 */
class UPE_Payment_Method {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Payment method definition.
	 *
	 * @var class-string<T>|null
	 */
	protected $definition;

	/**
	 * Stripe key name
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Display title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $is_reusable;

	/**
	 * Instance of WC Payments Token Service to save payment method
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * Supported presentment currencies for which charges for a payment method can be processed
	 * Empty if all currencies are supported
	 *
	 * @var string[]
	 */
	protected $currencies;

	/**
	 * Should payment method be restricted to only domestic payments.
	 * E.g. only to Stripe's connected account currency.
	 *
	 * @var boolean
	 */
	protected $accept_only_domestic_payment = false;

	/**
	 * Represent payment total limitations for the payment method (per-currency).
	 *
	 * @var array<string,array<string,array<string,int>>>
	 */
	protected $limits_per_currency = [];

	/**
	 * Payment method icon URL
	 *
	 * @var string
	 */
	protected $icon_url;

	/**
	 * Payment method icon URL for dark themes (optional)
	 *
	 * @var string
	 */
	protected $dark_icon_url;

	/**
	 * Is the payment method a BNPL (Buy Now Pay Later) method?
	 *
	 * @var boolean
	 */
	protected $is_bnpl = false;

	/**
	 * Supported customer locations for which charges for a payment method can be processed
	 * Empty if all customer locations are supported
	 *
	 * @var string[]
	 */
	protected $countries = [];

	/**
	 * Create instance of payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Instance of WC_Payments_Token_Service.
	 * @param class-string<T>|null      $definition    Optional payment method definition class name.
	 */
	public function __construct( $token_service, ?string $definition = null ) {
		$this->token_service = $token_service;
		$this->definition    = $definition;

		if ( null !== $this->definition ) {
			// Cache values that don't require context.
			$this->stripe_id                    = $this->definition::get_id();
			$this->is_reusable                  = $this->definition::is_reusable();
			$this->currencies                   = $this->definition::get_supported_currencies();
			$this->accept_only_domestic_payment = $this->definition::accepts_only_domestic_payments();
			$this->limits_per_currency          = $this->definition::get_limits_per_currency();
			$this->is_bnpl                      = $this->definition::is_bnpl();
			$this->countries                    = $this->definition::get_supported_countries();
		}
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		if ( null !== $this->definition ) {
			return $this->definition::get_id();
		}
		return $this->stripe_id;
	}

	/**
	 * Returns payment method title
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 *
	 * @phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_title( $account_country );
		}
		return $this->title;
	}

	/**
	 * Returns payment method currencies
	 *
	 * @return array
	 */
	public function get_currencies() {
		return $this->currencies;
	}

	/**
	 * Determines whether the payment method is restricted to the Stripe account's currency.
	 * E.g.: Afterpay/Clearpay and Affirm only supports domestic payments; Klarna also implements a simplified version of these market restrictions.
	 *
	 * @return bool
	 */
	public function has_domestic_transactions_restrictions() {
		return $this->accept_only_domestic_payment;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @param string $account_country Country of merchants account.
	 * @param bool   $skip_limits_per_currency_check Whether to skip limits per currency check.
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout( string $account_country, bool $skip_limits_per_currency_check = false ) {
		if ( $this->is_subscription_item_in_cart() || $this->is_changing_payment_method_for_subscription() ) {
			return $this->is_reusable();
		}

		// This part ensures that when payment limits for the currency declared, those will be respected (e.g. BNPLs).
		if ( [] !== $this->limits_per_currency && ! $skip_limits_per_currency_check ) {
			$order = null;
			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
				$order = is_a( $order, 'WC_Order' ) ? $order : null;
			}

			$currency = get_woocommerce_currency();
			if ( $order ) {
				$currency = $order->get_currency();
			}

			// If the currency limits are not defined, we allow the PM for now (gateway has similar validation for limits).
			$total = null;
			if ( $order ) {
				$total = $order->get_total();
			} elseif ( isset( WC()->cart ) ) {
				$total = WC()->cart->get_total( '' );
			}

			if ( isset( $this->limits_per_currency[ $currency ], WC()->cart ) && ! empty( $total ) ) {
				$amount = WC_Payments_Utils::prepare_amount( $total, $currency );

				if ( $amount > 0 ) {
					$range = null;
					if ( isset( $this->limits_per_currency[ $currency ][ $account_country ] ) ) {
						$range = $this->limits_per_currency[ $currency ][ $account_country ];
					} elseif ( isset( $this->limits_per_currency[ $currency ]['default'] ) ) {
						$range = $this->limits_per_currency[ $currency ]['default'];
					}
					// If there is no range specified for the currency-country pair we don't support it and return false.
					if ( null === $range ) {
						return false;
					}
					$is_valid_minimum = null === $range['min'] || $amount >= $range['min'];
					$is_valid_maximum = null === $range['max'] || $amount <= $range['max'];
					return $is_valid_minimum && $is_valid_maximum;
				}
			}
		}

		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_reusable() {
		return $this->is_reusable;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support BNPL (Buy Now Pay Later) payments
	 *
	 * @return bool
	 */
	public function is_bnpl() {
		return $this->is_bnpl;
	}

	/**
	 * Returns boolean dependent on whether payment method will accept charges
	 * with chosen currency
	 *
	 * @param string   $account_domestic_currency Domestic currency of the account.
	 * @param int|null $order_id                 Optional order ID, if order currency should take precedence.
	 *
	 * @return bool
	 */
	public function is_currency_valid( string $account_domestic_currency, $order_id = null ) {
		$current_store_currency = $this->get_currency( $order_id );
		if ( null === $current_store_currency ) {
			return false;
		}

		if ( $this->has_domestic_transactions_restrictions() ) {
			if ( strtolower( $current_store_currency ) !== strtolower( $account_domestic_currency ) ) {
				return false;
			}
		}

		$supported_currencies = $this->get_currencies();

		return empty( $supported_currencies ) || in_array( $current_store_currency, $supported_currencies, true );
	}

	/**
	 * Add payment method to user and return WC payment token
	 *
	 * @param WP_User $user User to get payment token from.
	 * @param string  $payment_method_id Stripe payment method ID string.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $payment_method_id ) {
		return $this->token_service->add_payment_method_to_user( $payment_method_id, $user );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_testing_instructions( $account_country );
		}
		return '';
	}

	/**
	 * Returns the payment method icon URL or an empty string.
	 *
	 * @param string|null $account_country Optional account country.
	 * @return string
	 *
	 * @phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public function get_icon( ?string $account_country = null ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_icon_url( $account_country );
		}
		return isset( $this->icon_url ) ? $this->icon_url : '';
	}

	/**
	 * Returns icon to use on dark themes.
	 *
	 * @param string|null $account_country Optional account country.
	 * @return string
	 */
	public function get_dark_icon( ?string $account_country = null ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_dark_icon_url( $account_country );
		}
		return isset( $this->dark_icon_url ) ? $this->dark_icon_url : $this->get_icon( $account_country );
	}

	/**
	 * Gets the theme appropriate icon for the payment method for a given location and context.
	 *
	 * @param string  $location The location to get the icon for.
	 * @param boolean $is_blocks Whether the icon is for blocks.
	 * @param string  $account_country Optional account country.
	 * @return string
	 */
	public function get_payment_method_icon_for_location( string $location = 'checkout', bool $is_blocks = true, ?string $account_country = null ) {
		$appearance_theme = WC_Payments_Utils::get_active_upe_theme_transient_for_location( $location, $is_blocks ? 'blocks' : 'classic' );

		if ( 'night' === $appearance_theme ) {
			return $this->get_dark_icon( $account_country );
		}

		return $this->get_icon( $account_country );
	}

	/**
	 * Returns payment method supported countries
	 *
	 * @return array
	 */
	public function get_countries() {
		$account         = \WC_Payments::get_account_service()->get_cached_account_data();
		$account_country = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';

		return $this->has_domestic_transactions_restrictions() ? [ $account_country ] : $this->countries;
	}

	/**
	 * Returns payment method description for the settings page.
	 *
	 * @param string|null $account_country Country of merchants account.
	 *
	 * @return string
	 */
	public function get_description( ?string $account_country = null ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_description( $account_country );
		}
		return '';
	}

	/**
	 * Returns payment method settings label.
	 *
	 * @param string $account_country Country of merchants account.
	 * @return string
	 */
	public function get_settings_label( string $account_country ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_settings_label( $account_country );
		}
		return $this->get_title( $account_country );
	}

	/**
	 * Returns payment method settings icon.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @return string
	 */
	public function get_settings_icon_url( ?string $account_country = null ) {
		if ( null !== $this->definition ) {
			return $this->definition::get_settings_icon_url( $account_country );
		}
		return $this->get_icon( $account_country );
	}

	/**
	 * Returns boolean dependent on whether payment method allows manual capture.
	 *
	 * @return bool
	 */
	public function allows_manual_capture() {
		if ( null !== $this->definition ) {
			return $this->definition::allows_manual_capture();
		}

		return false;
	}

	/**
	 * Returns the Stripe key for the payment method.
	 *
	 * @return string
	 */
	public function get_stripe_key() {
		if ( null !== $this->definition ) {
			return $this->definition::get_stripe_id();
		}
		return \WC_Payments::get_gateway()->get_payment_method_capability_key_map()[ $this->stripe_id ];
	}

	/**
	 * Returns payment method settings definition.
	 *
	 * @param string $account_country Country of merchants account.
	 * @return array
	 */
	public function get_payment_method_information_object( string $account_country ) {
		return [
			'id'                            => $this->get_id(),
			'label'                         => $this->get_settings_label( $account_country ),
			'description'                   => $this->get_description( $account_country ),
			'settings_icon_url'             => $this->get_settings_icon_url( $account_country ),
			'currencies'                    => $this->get_currencies(),
			'stripe_key'                    => $this->get_stripe_key(),
			'allows_manual_capture'         => $this->allows_manual_capture(),
			'allows_pay_later'              => $this->is_bnpl(),
			'accepts_only_domestic_payment' => $this->has_domestic_transactions_restrictions(),
		];
	}

	/**
	 * Returns valid currency to use to filter payment methods.
	 *
	 * @param int $order_id Optional order ID, if order currency should take precedence.
	 *
	 * @return string|null
	 */
	private function get_currency( $order_id = null ) {
		if ( is_wc_endpoint_url( 'order-pay' ) || null !== $order_id ) {
			global $wp;
			if ( null === $order_id ) {
				$order_id = absint( $wp->query_vars['order-pay'] );
			}
			$order = wc_get_order( $order_id );
			if ( false === $order ) {
				return null;
			}
			return $order->get_currency();
		}
		return get_woocommerce_currency();
	}
}
