<?php
/**
 * Class UPE_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_API_Payment_Intention;
use WC_Payments_API_Setup_Intention;
use WC_Payments_Fraud_Service;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Payment_Method;
use WCPay\Constants\Payment_Type;
use WCPay\Core\Server\Request\Create_Setup_Intention;
use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Logger;
use WCPay\Session_Rate_Limiter;
use Exception;
use WC_Order;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WC_Payment_Token_CC;
use WC_Payments_Token_Service;
use WC_Payment_Token_WCPay_SEPA;
use WC_Payments_Utils;
use WCPay\Duplicate_Payment_Prevention_Service;
use WP_User;
use WC_Payments_Localization_Service;
use WCPay\Payment_Information;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Exceptions\Invalid_Address_Exception;

/**
 * UPE Payment method extended from WCPay generic Gateway.
 */
class UPE_Payment_Gateway extends WC_Payment_Gateway_WCPay {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments';

	const METHOD_ENABLED_KEY = 'enabled';

	const UPE_APPEARANCE_TRANSIENT = 'wcpay_upe_appearance';

	const WC_BLOCKS_UPE_APPEARANCE_TRANSIENT = 'wcpay_wc_blocks_upe_appearance';

	const PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE = 'upe_process_redirect_order_id_mismatched';

	/**
	 * Array mapping payment method string IDs to classes
	 *
	 * @var UPE_Payment_Method[]
	 */
	protected $payment_methods = [];

	/**
	 * Generic gateway title to be displayed at checkout, if more than one payment method is enabled.
	 *
	 * @var string
	 */
	protected $checkout_title;

	/**
	 * UPE Payment Method for gateway.
	 *
	 * @var UPE_Payment_Method
	 */
	protected $payment_method;

	/**
	 * Stripe payment method type ID.
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * UPE Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client                  - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                              - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                     - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                        - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service             - Action Scheduler service instance.
	 * @param UPE_Payment_Method                   $payment_method                       - Specific UPE_Payment_Method instance for gateway.
	 * @param array                                $payment_methods                      - Array of UPE payment methods.
	 * @param Session_Rate_Limiter                 $failed_transaction_rate_limiter      - Session Rate Limiter instance.
	 * @param WC_Payments_Order_Service            $order_service                        - Order class instance.
	 * @param Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service - Service for preventing duplicate payments.
	 * @param WC_Payments_Localization_Service     $localization_service                 - Localization service instance.
	 * @param WC_Payments_Fraud_Service            $fraud_service                        - Fraud service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		UPE_Payment_Method $payment_method,
		array $payment_methods,
		Session_Rate_Limiter $failed_transaction_rate_limiter,
		WC_Payments_Order_Service $order_service,
		Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service,
		WC_Payments_Localization_Service $localization_service,
		WC_Payments_Fraud_Service $fraud_service
	) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service, $failed_transaction_rate_limiter, $order_service, $duplicate_payment_prevention_service, $localization_service, $fraud_service );
		$this->title              = $payment_method->get_title();
		$this->method_description = __( 'Payments made simple, with no monthly fees - designed exclusively for WooCommerce stores. Accept credit cards, debit cards, and other popular payment methods.', 'woocommerce-payments' );
		$this->description        = '';
		$this->checkout_title     = __( 'Popular payment methods', 'woocommerce-payments' );
		$this->payment_methods    = $payment_methods;

		$this->stripe_id      = $payment_method->get_id();
		$this->payment_method = $payment_method;
		$this->icon           = $payment_method->get_icon();

		if ( 'card' !== $this->stripe_id ) {
			$this->id           = self::GATEWAY_ID . '_' . $this->stripe_id;
			$this->method_title = "WooPayments ($this->title)";
		}
	}

	/**
	 * Displays HTML tags for WC payment gateway radio button content.
	 */
	public function display_gateway_html() {
		?>
			<div class="wcpay-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
		<?php
	}

	/**
	 * Gets UPE_Payment_Method instance from ID.
	 *
	 * @param string $payment_method_type Stripe payment method type ID.
	 * @return UPE_Payment_Method|false UPE payment method instance.
	 */
	public function get_selected_payment_method( $payment_method_type ) {
		return WC_Payments::get_payment_method_by_id( $payment_method_type );
	}

	/**
	 * Create and confirm payment intent. Function used to route any payments that do not use the UPE flow through the parent process payment.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function parent_process_payment( $order_id ) {
		return parent::process_payment( $order_id );
	}

	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		do_action( 'wc_payments_set_gateway', $this->get_selected_stripe_payment_type_id() );
		do_action( 'wc_payments_add_upe_payment_fields' );
	}

	/**
	 * Update payment intent for completed checkout and return redirect URL for Stripe to confirm payment.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 * @throws Exception Error processing the payment.
	 * @throws Order_Not_Found_Exception
	 */
	public function process_payment( $order_id ) {
		$_POST['wcpay_selected_upe_payment_type'] = $this->stripe_id;
		$order                                    = wc_get_order( $order_id );

		if ( 20 < strlen( $order->get_billing_phone() ) ) {
			throw new Process_Payment_Exception(
				__( 'Invalid phone number.', 'woocommerce-payments' ),
				'invalid_phone_number'
			);
		}
		$payment_intent_id         = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$amount                    = $order->get_total();
		$currency                  = $order->get_currency();
		$converted_amount          = WC_Payments_Utils::prepare_amount( $amount, $currency );
		$payment_needed            = 0 < $converted_amount;
		$selected_upe_payment_type = ! empty( $_POST['wcpay_selected_upe_payment_type'] ) ? wc_clean( wp_unslash( $_POST['wcpay_selected_upe_payment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_type              = $this->is_payment_recurring( $order_id ) ? Payment_Type::RECURRING() : Payment_Type::SINGLE();
		$save_payment_method       = $payment_type->equals( Payment_Type::RECURRING() ) || ! empty( $_POST[ 'wc-' . $this->id . '-new-payment-method' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_country           = ! empty( $_POST['wcpay_payment_country'] ) ? wc_clean( wp_unslash( $_POST['wcpay_payment_country'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $payment_intent_id ) {
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			if ( $payment_needed ) {
				// Check if session exists before instantiating Fraud_Prevention_Service.
				if ( WC()->session ) {
					$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
					// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					if ( $fraud_prevention_service->is_enabled() && ! $fraud_prevention_service->verify_token( $_POST['wcpay-fraud-prevention-token'] ?? null ) ) {
						throw new Process_Payment_Exception(
							__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
							'fraud_prevention_enabled'
						);
					}
				}

				if ( $this->failed_transaction_rate_limiter->is_limited() ) {
					// Throwing an exception instead of adding an error notice
					// makes the error notice show up both in the regular and block checkout.
					throw new Exception( __( 'Your payment was not processed.', 'woocommerce-payments' ) );
				}

				// Try catching the error without reaching the API.
				$minimum_amount = WC_Payments_Utils::get_cached_minimum_amount( $currency );
				if ( $minimum_amount > $converted_amount ) {
					$exception = new Amount_Too_Small_Exception( 'Amount too small', $minimum_amount, $currency, 400 );
					throw new Exception( WC_Payments_Utils::get_filtered_error_message( $exception ) );
				}

				$check_session_order = $this->duplicate_payment_prevention_service->check_against_session_processing_order( $order );
				if ( is_array( $check_session_order ) ) {
					return $check_session_order;
				}
				$this->duplicate_payment_prevention_service->maybe_update_session_processing_order( $order_id );

				$check_existing_intention = $this->duplicate_payment_prevention_service->check_payment_intent_attached_to_order_succeeded( $order );
				if ( is_array( $check_existing_intention ) ) {
					return $check_existing_intention;
				}

				// @toDo: This is now not used?
				$additional_api_parameters = $this->get_mandate_params_for_order( $order );

				try {
					$payment_methods = $this->get_selected_upe_payment_methods( (string) $selected_upe_payment_type, $this->get_payment_method_ids_enabled_at_checkout( null, true ) ?? [] );

					$request = Update_Intention::create( $payment_intent_id );
					$request->set_currency_code( strtolower( $currency ) );
					$request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $currency ) );
					$request->set_metadata( $this->get_metadata_from_order( $order, $payment_type ) );
					$request->set_level3( $this->get_level3_data_from_order( $order ) );
					$request->set_payment_method_types( $payment_methods );
					$request->set_hook_args( $order, $payment_intent_id );
					if ( $payment_country ) {
						$request->set_payment_country( $payment_country );
					}
					if ( true === $save_payment_method ) {
						$request->setup_future_usage();
					}
					if ( $customer_id ) {
						$request->set_customer( $customer_id );
					}
					$payment_method_options = $this->get_mandate_params_for_order( $order );
					if ( $payment_method_options ) {
						$request->setup_future_usage();
						$request->set_payment_method_options( $payment_method_options );
					}
					$updated_payment_intent = $request->send();
				} catch ( Amount_Too_Small_Exception $e ) {
					// This code would only be reached if the cache has already expired.
					throw new Exception( WC_Payments_Utils::get_filtered_error_message( $e ) );
				} catch ( API_Exception $e ) {
					if ( 'wcpay_blocked_by_fraud_rule' === $e->get_error_code() ) {
						$this->order_service->mark_order_blocked_for_fraud( $order, $payment_intent_id, Intent_Status::CANCELED );
					}
					throw $e;
				}

				$intent_id              = $updated_payment_intent->get_id();
				$intent_status          = $updated_payment_intent->get_status();
				$payment_method         = $updated_payment_intent->get_payment_method_id();
				$charge                 = $updated_payment_intent->get_charge();
				$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
				$payment_method_type    = $this->get_payment_method_type_from_payment_details( $payment_method_details );
				$charge_id              = $charge ? $charge->get_id() : null;

				/**
				 * Attach the intent and exchange info to the order before doing the redirect, just in case the redirect
				 * either does not complete properly, or the Stripe webhook which processes a successful order hits before
				 * the redirect completes.
				 */
				$this->order_service->attach_intent_info_to_order( $order, $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency );
				$this->attach_exchange_info_to_order( $order, $charge_id );
				$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
				if ( Intent_Status::SUCCEEDED === $intent_status ) {
					$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
				}
				$this->order_service->update_order_status_from_intent( $order, $updated_payment_intent );

				$last_payment_error_code = $updated_payment_intent->get_last_payment_error()['code'] ?? '';
				if ( $this->should_bump_rate_limiter( $last_payment_error_code ) ) {
					// UPE method gives us the error of the previous payment attempt, so we use that for the Rate Limiter.
					$this->failed_transaction_rate_limiter->bump();
				}
			}
		} else {
			return $this->parent_process_payment( $order_id );
		}

		return [ // nosemgrep: audit.php.wp.security.xss.query-arg  -- The output of add_query_arg is being escaped.
			'result'         => 'success',
			'payment_needed' => $payment_needed,
			'redirect_url'   => wp_sanitize_redirect(
				esc_url_raw(
					add_query_arg(
						[
							'wc_payment_method'   => self::GATEWAY_ID,
							'_wpnonce'            => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
							'save_payment_method' => $save_payment_method ? 'yes' : 'no',
						],
						$this->get_return_url( $order )
					)
				)
			),
		];
	}

	/**
	 * Returns true when viewing payment methods page.
	 *
	 * @return bool
	 */
	private function is_payment_methods_page() {
		global $wp;

		$page_id = wc_get_page_id( 'myaccount' );

		return ( $page_id && is_page( $page_id ) && ( isset( $wp->query_vars['payment-methods'] ) ) );
	}

	/**
	 * Get selected UPE payment methods.
	 *
	 * @param string $selected_upe_payment_type Selected payment methods.
	 * @param array  $enabled_payment_methods Enabled payment methods.
	 *
	 * @return array
	 */
	protected function get_selected_upe_payment_methods( string $selected_upe_payment_type, array $enabled_payment_methods ) {
		$payment_methods = [];
		if ( '' !== $selected_upe_payment_type ) {
			// Only update the payment_method_types if we have a reference to the payment type the customer selected.
			$payment_methods[] = $selected_upe_payment_type;

			if ( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID === $selected_upe_payment_type ) {
				$is_link_enabled = in_array(
					Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
					$enabled_payment_methods,
					true
				);
				if ( $is_link_enabled ) {
					$payment_methods[] = Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
				}
			}
		}
		return $payment_methods;
	}
	/**
	 * Check for a redirect payment method on order received page or setup intent on payment methods page.
	 */
	public function maybe_process_upe_redirect() {
		if ( $this->is_payment_methods_page() ) {
			// If a payment method was added using UPE, we need to clear the cache and notify the user.
			if ( $this->is_setup_intent_success_creation_redirection() ) {
					wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-payments' ) );
					$user = wp_get_current_user();
					$this->customer_service->clear_cached_payment_methods_for_user( $user->ID );
			}
			return;
		}

		if ( ! is_order_received_page() ) {
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : '';
		if ( self::GATEWAY_ID !== $payment_method ) {
			return;
		}

		$is_nonce_valid = check_admin_referer( 'wcpay_process_redirect_order_nonce' );
		if ( ! $is_nonce_valid || empty( $_GET['wc_payment_method'] ) ) {
			return;
		}

		if ( ! empty( $_GET['payment_intent_client_secret'] ) ) {
			$intent_id_from_request = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : '';
		} elseif ( ! empty( $_GET['setup_intent_client_secret'] ) ) {
			$intent_id_from_request = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
		} else {
			return;
		}

		$order_id               = absint( get_query_var( 'order-received' ) );
		$order_key_from_request = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$save_payment_method    = isset( $_GET['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_GET['save_payment_method'] ) ) : false;

		if ( empty( $intent_id_from_request ) || empty( $order_id ) || empty( $order_key_from_request ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			// the ID of non-existing order was passed in.
			return;
		}

		if ( $order->get_order_key() !== $order_key_from_request ) {
			// Valid return url should have matching order key.
			return;
		}

		// Perform additional checks for non-zero-amount. For zero-amount orders, we can't compare intents because they are not attached to the order at this stage.
		// Once https://github.com/Automattic/woocommerce-payments/issues/6575 is closed, this check can be applied for zero-amount orders as well.
		if ( $order->get_total() > 0 && ! $this->is_proper_intent_used_with_order( $order, $intent_id_from_request ) ) {
			return;
		}

		$this->process_redirect_payment( $order, $intent_id_from_request, $save_payment_method );
	}

	/**
	 * Processes redirect payments.
	 *
	 * @param WC_Order $order The order being processed.
	 * @param string   $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param bool     $save_payment_method Boolean representing whether payment method for order should be saved.
	 *
	 * @throws Process_Payment_Exception When the payment intent has an error.
	 */
	public function process_redirect_payment( $order, $intent_id, $save_payment_method ) {
		try {
			$order_id = $order->get_id();
			if ( $order->has_status(
				[
					Order_Status::PROCESSING,
					Order_Status::COMPLETED,
					Order_Status::ON_HOLD,
				]
			) ) {
				return;
			}

			Logger::log( "Begin processing UPE redirect payment for order {$order_id} for the amount of {$order->get_total()}" );

			// Get user/customer for order.
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			$payment_needed = 0 < $order->get_total();

			// Get payment intent to confirm status.
			if ( $payment_needed ) {
				$request = Get_Intention::create( $intent_id );
				$request->set_hook_args( $order );
				/** @var WC_Payments_API_Payment_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent                 = $request->send();
				$client_secret          = $intent->get_client_secret();
				$status                 = $intent->get_status();
				$charge                 = $intent->get_charge();
				$charge_id              = $charge ? $charge->get_id() : null;
				$currency               = $intent->get_currency();
				$payment_method_id      = $intent->get_payment_method_id();
				$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
				$payment_method_type    = $this->get_payment_method_type_from_payment_details( $payment_method_details );
				$error                  = $intent->get_last_payment_error();

				// This check applies to payment intents only due to two reasons:
				// (1) metadata is missed for setup intents. See https://github.com/Automattic/woocommerce-payments/issues/6575.
				// (2) most issues so far affect only payment intents.
				$intent_metadata = is_array( $intent->get_metadata() ) ? $intent->get_metadata() : [];
				$this->validate_order_id_received_vs_intent_meta_order_id( $order, $intent_metadata );
			} else {
				$request = Get_Setup_Intention::create( $intent_id );
				/** @var WC_Payments_API_Setup_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent                 = $request->send();
				$client_secret          = $intent->get_client_secret();
				$status                 = $intent->get_status();
				$charge_id              = '';
				$charge                 = null;
				$currency               = $order->get_currency();
				$payment_method_id      = $intent->get_payment_method_id();
				$payment_method_details = false;
				$payment_method_type    = $intent->get_payment_method_type();
				$error                  = $intent->get_last_setup_error();
			}

			if ( ! empty( $error ) ) {
				Logger::log( 'Error when processing payment: ' . $error['message'] );
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'upe_payment_intent_error'
				);
			} else {
				$payment_method = $this->get_selected_payment_method( $payment_method_type );
				if ( ! $payment_method ) {
					return;
				}

				if ( $save_payment_method && $payment_method->is_reusable() ) {
					try {
						$token = $payment_method->get_payment_token_for_user( $user, $payment_method_id );
						$this->add_token_to_order( $order, $token );
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

				$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method_id, $customer_id, $charge_id, $currency );
				$this->attach_exchange_info_to_order( $order, $charge_id );
				if ( Intent_Status::SUCCEEDED === $status ) {
					$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
				}
				$this->order_service->update_order_status_from_intent( $order, $intent );
				$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
				$this->order_service->attach_transaction_fee_to_order( $order, $charge );

				if ( Intent_Status::REQUIRES_ACTION === $status ) {
					// I don't think this case should be possible, but just in case...
					$next_action = $intent->get_next_action();
					if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
						wp_safe_redirect( $next_action['redirect_to_url']['url'] );
						exit;
					} else {
						$redirect_url = sprintf(
							'#wcpay-confirm-%s:%s:%s:%s',
							$payment_needed ? 'pi' : 'si',
							$order_id,
							WC_Payments_Utils::encrypt_client_secret( $this->account->get_stripe_account_id(), $client_secret ),
							wp_create_nonce( 'wcpay_update_order_status_nonce' )
						);
						wp_safe_redirect( $redirect_url );
						exit;
					}
				}
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error: ' . $e->getMessage() );

			$is_order_id_mismatched_exception =
				is_a( $e, Process_Payment_Exception::class )
				&& self::PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE === $e->get_error_code();

			// If the order ID mismatched exception is thrown, do not mark the order as failed.
			// Because the outcome of the payment intent is for another order, not for the order processed here.
			if ( ! $is_order_id_mismatched_exception ) {
				// Confirm our needed variables are set before using them due to there could be a server issue during the get_intent process.
				$status    = $status ?? null;
				$charge_id = $charge_id ?? null;

				/* translators: localized exception message */
				$message = sprintf( __( 'UPE payment failed: %s', 'woocommerce-payments' ), $e->getMessage() );
				$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id, $message );
			}

			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error' );

			$redirect_url = wc_get_checkout_url();
			if ( $is_order_id_mismatched_exception ) {
				$redirect_url = add_query_arg( self::PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE, 'yes', $redirect_url );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Verifies that the proper intent is used to process the order.
	 *
	 * @param WC_Order $order The order object based on the order_id received from the request.
	 * @param string   $intent_id_from_request The intent ID received from the request.
	 *
	 * @return bool True if the proper intent is used to process the order, false otherwise.
	 */
	public function is_proper_intent_used_with_order( $order, $intent_id_from_request ) {
		$intent_id_attached_to_order = $this->order_service->get_intent_id_for_order( $order );
		if ( ! hash_equals( $intent_id_attached_to_order, $intent_id_from_request ) ) {
			Logger::error(
				sprintf(
					'Intent ID mismatch. Received in request: %1$s. Attached to order: %2$s. Order ID: %3$d',
					$intent_id_from_request,
					$intent_id_attached_to_order,
					$order->get_id()
				)
			);
			return false;
		}
		return true;
	}

	/**
	 * Generates the configuration values, needed for UPE payment fields.
	 *
	 * @deprecated 5.0.0
	 *
	 * @return array
	 */
	public function get_payment_fields_js_config() {
		wc_deprecated_function( __FUNCTION__, '5.0.0', 'WC_Payments_Checkout::get_payment_fields_js_config' );
		return WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config();
	}

	/**
	 * True if the request contains the values that indicates a redirection after a successful setup intent creation.
	 *
	 * @return bool
	 */
	public function is_setup_intent_success_creation_redirection() {
		return ! empty( $_GET['setup_intent_client_secret'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $_GET['setup_intent'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $_GET['redirect_status'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'succeeded' === $_GET['redirect_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Adds a token to current user from a setup intent id.
	 *
	 * @param string  $setup_intent_id ID of the setup intent.
	 * @param WP_User $user            User to add token to.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA|null The added token.
	 */
	public function create_token_from_setup_intent( $setup_intent_id, $user ) {
		try {
			$setup_intent_request = Get_Setup_Intention::create( $setup_intent_id );
			/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$setup_intent = $setup_intent_request->send();

			$payment_method_id = $setup_intent->get_payment_method_id();
			// TODO: When adding SEPA and Sofort, we will need a new API call to get the payment method and from there get the type.
			// Leaving 'card' as a hardcoded value for now to avoid the extra API call.
			// $payment_method = $this->payment_methods['card'];// Maybe this should be enforced.
			$payment_method = $this->payment_method;

			return $payment_method->get_payment_token_for_user( $user, $payment_method_id );
		} catch ( Exception $e ) {
			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error', [ 'icon' => 'error' ] );
			Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
		}
	}

	/**
	 * Mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed.
	 * This applies to SEPA and Link payment methods.
	 * https://stripe.com/docs/payments/finalize-payments-on-the-server
	 *
	 * @return boolean True if mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed, false otherwise.
	 */
	public function is_mandate_data_required() {
		$is_stripe_link_enabled = Payment_Method::CARD === $this->get_selected_stripe_payment_type_id() && in_array( Payment_Method::LINK, $this->get_upe_enabled_payment_method_ids(), true );
		$is_sepa_debit_payment  = Payment_Method::SEPA === $this->get_selected_stripe_payment_type_id();

		return $is_stripe_link_enabled || $is_sepa_debit_payment;
	}

	/**
	 * Returns the Stripe payment type of the selected payment method.
	 *
	 * @return string
	 */
	public function get_selected_stripe_payment_type_id() {
		return $this->stripe_id;
	}

	/**
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param \WC_Order  $order WC Order being processed.
	 * @param string     $payment_method_type Stripe payment method key.
	 * @param array|bool $payment_method_details Array of payment method details from charge or false.
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details ) {
		$payment_method = $this->get_selected_payment_method( $payment_method_type );
		if ( ! $payment_method ) {
			return;
		}

		$payment_method_title = $payment_method->get_title( $payment_method_details );

		$payment_gateway = in_array( $payment_method->get_id(), [ Payment_Method::CARD, Payment_Method::LINK ], true ) ? self::GATEWAY_ID : self::GATEWAY_ID . '_' . $payment_method_type;

		$order->set_payment_method( $payment_gateway );
		$order->set_payment_method_title( $payment_method_title );
		$order->save();
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout.
	 *
	 * @param string $order_id optional Order ID.
	 * @param bool   $force_currency_check optional Whether the currency check is required even if is_admin().
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids_enabled_at_checkout( $order_id = null, $force_currency_check = false ) {
		$automatic_capture = empty( $this->get_option( 'manual_capture' ) ) || $this->get_option( 'manual_capture' ) === 'no';
		if ( $automatic_capture ) {
			$upe_enabled_payment_methods = $this->get_upe_enabled_payment_method_ids();
		} else {
			$upe_enabled_payment_methods = array_intersect( $this->get_upe_enabled_payment_method_ids(), [ Payment_Method::CARD, Payment_Method::LINK ] );
		}
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$force_currency_check = true;
		}

		$enabled_payment_methods = [];
		$active_payment_methods  = $this->get_upe_enabled_payment_method_statuses();

		foreach ( $upe_enabled_payment_methods as $payment_method_id ) {
			$payment_method_capability_key = $this->payment_method_capability_key_map[ $payment_method_id ] ?? 'undefined_capability_key';
			if ( isset( $this->payment_methods[ $payment_method_id ] ) ) {
				// When creating a payment intent, we need to ensure the currency is matching
				// with the payment methods which are sent with the payment intent request, otherwise
				// Stripe returns an error.

				// force_currency_check = 0 is_admin = 0 currency_is_checked = 1.
				// force_currency_check = 0 is_admin = 1 currency_is_checked = 0.
				// force_currency_check = 1 is_admin = 0 currency_is_checked = 1.
				// force_currency_check = 1 is_admin = 1 currency_is_checked = 1.

				$skip_currency_check       = ! $force_currency_check && is_admin();
				$processing_payment_method = $this->payment_methods[ $payment_method_id ];
				if ( $processing_payment_method->is_enabled_at_checkout( $this->get_account_country() ) && ( $skip_currency_check || $processing_payment_method->is_currency_valid( $this->get_account_domestic_currency(), $order_id ) ) ) {
					$status = $active_payment_methods[ $payment_method_capability_key ]['status'] ?? null;
					if ( 'active' === $status ) {
						$enabled_payment_methods[] = $payment_method_id;
					}
				}
			}
		}

		// if credit card payment method is not enabled, we don't use stripe link.
		if (
			! in_array( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true ) &&
			in_array( Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true ) ) {
			$enabled_payment_methods = array_filter(
				$enabled_payment_methods,
				static function( $method ) {
					return Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID !== $method;
				}
			);
		}

		return $enabled_payment_methods;
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout filtered by fees.
	 *
	 * @param string $order_id optional Order ID.
	 * @param bool   $force_currency_check optional Whether the currency check is required even if is_admin().
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids_enabled_at_checkout_filtered_by_fees( $order_id = null, $force_currency_check = false ) {
		$enabled_payment_methods = $this->get_payment_method_ids_enabled_at_checkout( $order_id, $force_currency_check );
		$methods_with_fees       = array_keys( $this->account->get_fees() );

		return array_values( array_intersect( $enabled_payment_methods, $methods_with_fees ) );
	}

	/**
	 * Returns the list of available payment method types for UPE.
	 * Filtering out those without configured fees, this will prevent a payment method not supported by the Stripe account's country from being returned.
	 * Note that we are not taking into account capabilities, which are taken into account when managing payment methods in settings.
	 * See https://stripe.com/docs/stripe-js/payment-element#web-create-payment-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		$available_methods = parent::get_upe_available_payment_methods();

		$available_methods[] = Becs_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Bancontact_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Eps_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Ideal_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Sofort_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Affirm_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID;

		$available_methods = array_values(
			apply_filters(
				'wcpay_upe_available_payment_methods',
				$available_methods
			)
		);

		$methods_with_fees = array_keys( $this->account->get_fees() );

		return array_values( array_intersect( $available_methods, $methods_with_fees ) );
	}

	/**
	 * Handle AJAX request for saving UPE appearance value to transient.
	 *
	 * @throws Exception - If nonce or setup intent is invalid.
	 */
	public function save_upe_appearance_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_save_upe_appearance_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception(
					__( 'Unable to update UPE appearance values at this time.', 'woocommerce-payments' )
				);
			}

			$is_blocks_checkout = isset( $_POST['is_blocks_checkout'] ) ? rest_sanitize_boolean( wc_clean( wp_unslash( $_POST['is_blocks_checkout'] ) ) ) : false;
			$appearance         = isset( $_POST['appearance'] ) ? json_decode( wc_clean( wp_unslash( $_POST['appearance'] ) ) ) : null;

			$appearance_transient = $is_blocks_checkout ? self::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT : self::UPE_APPEARANCE_TRANSIENT;

			if ( null !== $appearance ) {
				set_transient( $appearance_transient, $appearance, DAY_IN_SECONDS );
			}

			wp_send_json_success( $appearance, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e ),
			);
		}
	}

	/**
	 * Clear the saved UPE appearance transient value.
	 */
	public function clear_upe_appearance_transient() {
		delete_transient( self::UPE_APPEARANCE_TRANSIENT );
		delete_transient( self::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT );
	}

	/**
	 * Validate order_id received from the request vs value saved in the intent metadata.
	 * Throw an exception if they're not matched.
	 *
	 * @param  WC_Order $order The received order to process.
	 * @param  array    $intent_metadata The metadata of attached intent to the order.
	 *
	 * @return void
	 * @throws Process_Payment_Exception
	 */
	private function validate_order_id_received_vs_intent_meta_order_id( WC_Order $order, array $intent_metadata ): void {
		$intent_meta_order_id_raw = $intent_metadata['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;

		if ( $order->get_id() !== $intent_meta_order_id ) {
			Logger::error(
				sprintf(
					'UPE Process Redirect Payment - Order ID mismatched. Received: %1$d. Intent Metadata Value: %2$d',
					$order->get_id(),
					$intent_meta_order_id
				)
			);

			throw new Process_Payment_Exception(
				__( "We're not able to process this payment due to the order ID mismatch. Please try again later.", 'woocommerce-payments' ),
				self::PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE
			);
		}
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @deprecated 5.0.0
	 *
	 * @return array
	 */
	private function get_enabled_payment_method_config() {
		wc_deprecated_function( __FUNCTION__, '5.0.0', 'WC_Payments_Checkout::get_enabled_payment_method_config' );
		return WC_Payments::get_wc_payments_checkout()->get_enabled_payment_method_config();
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods that support saved payments
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	public function is_enabled_for_saved_payments( $payment_method_id ) {
		$payment_method = $this->get_selected_payment_method( $payment_method_id );
		if ( ! $payment_method ) {
			return false;
		}
		return $payment_method->is_reusable()
			&& ( is_admin() || $payment_method->is_currency_valid( $this->get_account_domestic_currency() ) );
	}

	/**
	 * Returns boolean for whether payment gateway supports saved payments.
	 *
	 * @return bool True, if gateway supports saved payments. False, otherwise.
	 */
	public function should_support_saved_payments() {
		return $this->is_enabled_for_saved_payments( $this->stripe_id );
	}

	/**
	 * Whether we should use the platform account to initialize Stripe on the checkout page.
	 *
	 * @return bool Result of the WCPay gateway checks if the card payment method is used, false otherwise.
	 */
	public function should_use_stripe_platform_on_checkout_page() {
		if ( 'card' === $this->stripe_id ) {
			return parent::should_use_stripe_platform_on_checkout_page();
		}
		return false;
	}

	/**
	 * This method is used by WooCommerce Core's WC_Payment_Gateways::get_available_payment_gateways() to filter out gateways.
	 *
	 * The availability decision includes an additional business rule that checks if the payment method is enabled at checkout
	 * via the is_enabled_at_checkout method. This method provides crucial information, among others, to determine if the gateway
	 * is reusable in case there's a subcription in the cart.
	 *
	 * @return bool Whether the gateway is enabled and ready to accept payments.
	 */
	public function is_available() {
		$processing_payment_method = $this->payment_methods[ $this->payment_method->get_id() ];
		if ( ! $processing_payment_method->is_enabled_at_checkout( $this->get_account_country() ) ) {
			return false;
		}
		return parent::is_available();
	}

	/**
	 * Log UPE Payment Errors on Checkout.
	 *
	 * @throws Exception If nonce is not present or invalid or charge ID is empty or order not found.
	 */
	public function log_payment_error_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_log_payment_error_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( 'Invalid request.' );
			}

			$charge_id = isset( $_POST['charge_id'] ) ? wc_clean( wp_unslash( $_POST['charge_id'] ) ) : '';
			if ( empty( $charge_id ) ) {
				throw new Exception( 'Charge ID cannot be empty.' );
			}

			// Get charge data from WCPay Server.
			$request = Get_Charge::create( $charge_id );
			$request->set_hook_args( $charge_id );
			$charge_data = $request->send();
			$order_id    = $charge_data['metadata']['order_id'];

			// Validate Order ID and proceed with logging errors and updating order status.
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception( 'Order not found. Unable to log error.' );
			}

			$intent_id = $charge_data['payment_intent'] ?? $order->get_meta( '_intent_id' );

			$request = Get_Intention::create( $intent_id );
			$request->set_hook_args( $order );
			$intent = $request->send();

			$intent_status = $intent->get_status();
			$error_message = esc_html( rtrim( $charge_data['failure_message'], '.' ) );

			$this->order_service->mark_payment_failed( $order, $intent_id, $intent_status, $charge_id, $error_message );

			wp_send_json_success();
		} catch ( Exception $e ) {
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e ),
			);
		}
	}

	/**
	 * This function wraps WC_Payments::get_payment_method_map, useful for unit testing.
	 *
	 * @return array Array of UPE_Payment_Method instances.
	 */
	public function wc_payments_get_payment_method_map() {
		return WC_Payments::get_payment_method_map();
	}

	/**
	 * Returns the checkout tile.
	 *
	 * @return string Checkout title.
	 */
	public function get_checkout_title() {
		return $this->checkout_title;
	}

	/**
	 * Returns the payment methods for this gateway.
	 *
	 * @return array|UPE_Payment_Method[]
	 */
	public function get_payment_methods() {
		return $this->payment_methods;
	}

	/**
	 * Returns the UPE payment method for the gateway.
	 *
	 * @return UPE_Payment_Method
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns Stripe payment method type ID.
	 *
	 * @return string
	 */
	public function get_stripe_id() {
		return $this->stripe_id;
	}

	/**
	 * Return the payment method type from the payment method details.
	 *
	 * @param array $payment_method_details Payment method details.
	 * @return string|null Payment method type or nothing.
	 */
	private function get_payment_method_type_from_payment_details( $payment_method_details ) {
		return $payment_method_details['type'] ?? null;
	}

	/**
	 * This function wraps WC_Payments::get_payment_gateway_by_id, useful for unit testing.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|UPE_Payment_Gateway Matching UPE Payment Gateway instance.
	 */
	public function wc_payments_get_payment_gateway_by_id( $payment_method_id ) {
		return WC_Payments::get_payment_gateway_by_id( $payment_method_id );
	}

	/**
	 * This function wraps WC_Payments::get_payment_method_by_id, useful for unit testing.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|UPE_Payment_Method Matching UPE Payment Method instance.
	 */
	public function wc_payments_get_payment_method_by_id( $payment_method_id ) {
		return WC_Payments::get_payment_method_by_id( $payment_method_id );
	}

	/**
	 * Handles the shipping requirement for Afterpay payments.
	 *
	 * This method extracts the shipping and billing data from the order and sets the appropriate
	 * shipping data for the Afterpay payment request. If neither shipping nor billing data is valid
	 * for shipping, an exception is thrown.
	 *
	 * @param WC_Order                     $order    The order object containing shipping and billing information.
	 * @param Create_And_Confirm_Intention $request The Afterpay payment request object to set shipping data on.
	 *
	 * @throws Invalid_Address_Exception If neither shipping nor billing address is valid for Afterpay payments.
	 * @return void
	 */
	private function handle_afterpay_shipping_requirement( WC_Order $order, Create_And_Confirm_Intention $request ): void {
		$check_if_usable = function( array $address ): bool {
			return $address['country'] && $address['state'] && $address['city'] && $address['postal_code'] && $address['line1'];
		};

		$shipping_data = $this->order_service->get_shipping_data_from_order( $order );
		if ( $check_if_usable( $shipping_data['address'] ) ) {
			$request->set_shipping( $shipping_data );
			return;
		}

		$billing_data = $this->order_service->get_billing_data_from_order( $order );
		if ( $check_if_usable( $billing_data['address'] ) ) {
			$request->set_shipping( $billing_data );
			return;
		}

		throw new Invalid_Address_Exception( __( 'A valid shipping address is required for Afterpay payments.', 'woocommerce-payments' ) );
	}


	/**
	 * Modifies the create intent parameters when processing a payment.
	 *
	 * If the selected Stripe payment type is AFTERPAY, it updates the shipping data in the request.
	 *
	 * @param Create_And_Confirm_Intention $request               The request object for creating and confirming intention.
	 * @param Payment_Information          $payment_information   The payment information object.
	 * @param WC_Order                     $order                 The order object.
	 *
	 * @return void
	 */
	protected function modify_create_intent_parameters_when_processing_payment( Create_And_Confirm_Intention $request, Payment_Information $payment_information, WC_Order $order ): void {
		if ( Payment_Method::AFTERPAY === $this->get_selected_stripe_payment_type_id() ) {
			$this->handle_afterpay_shipping_requirement( $order, $request );
		}
	}
}
