<?php
/**
 * Class OrderMetaHelper
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency\Helpers;

use WC_Payments_API_Client;
use WC_Payments_Features;
use WC_Payments_Utils;
use WCPay\MultiCurrency\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls OrderMetaHelper functionality.
 */
class OrderMetaHelper {
	/**
	 * The Multi-Currency instance.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Client for making requests to the WooCommerce Payments API.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Array of errors, if any.
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency          $multi_currency      The Multi-Currency instance.
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( MultiCurrency $multi_currency, WC_Payments_API_Client $payments_api_client ) {
		add_action( 'add_meta_boxes', [ $this, 'maybe_add_meta_box' ], 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'maybe_update_exchange_rate' ] );
		add_filter( 'woocommerce_debug_tools', [ $this, 'add_tool_button' ] );

		$this->multi_currency      = $multi_currency;
		$this->payments_api_client = $payments_api_client;
	}

	public function add_tool_button( $tools ) {
		$tools['wcpay_mc_meta_helper_toggle'] = [
			'name'   => __( 'Enable/Disable Multi-Currency Meta Helper', 'woocommerce-payments' ),
			'button' => __( 'Enable/Disable', 'woocommerce-payments' ),
			'desc'   => __( 'This tool can be used to fix meta data on orders related to Multi-Currency conversion rates.', 'woocommerce-payments' ),
			'callback' => [ $this, 'toggle_helper_option' ],
		];

		return $tools;
	}

	public function toggle_helper_option() {
		if ( WC_Payments_Features::is_mc_order_meta_helper_enabled() ) {
			update_option( WC_Payments_Features::MC_ORDER_META_HELPER_FLAG_NAME, 0 );
			return __( 'Multi-Currency Meta Helper has been disabled', 'woocommerce-payments' );
		} else {
			update_option( WC_Payments_Features::MC_ORDER_META_HELPER_FLAG_NAME, 1 );
			return __( 'Multi-Currency Meta Helper has been enabled', 'woocommerce-payments' );
		}
	}

	public function maybe_update_exchange_rate( $order_id ) {
		$order = $this->confirm_actions( $order_id );
		if ( ! $order || empty( $_POST['wcpay_multi_currency_exchange_rate'] ) ) {
			return;
		}

		$new_exchange_rate = sanitize_text_field( $_POST['wcpay_multi_currency_exchange_rate'] );

		if ( ! is_numeric( $new_exchange_rate ) ) {
			$this->add_error( $order, 'Exchange rate value not numeric: ' . $new_exchange_rate );
		}

		if ( $this->has_errors( $order ) ) {
			return;
		}

		$old_exchange_rate = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' );
		

		$note = __( 'New exchange rate: ' . $new_exchange_rate, 'woocommerce-payments' );
		$order->add_order_note( $note, 0, true );

		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', $_POST['wcpay_multi_currency_exchange_rate'] );
		$order->save();
	}

	/**
	 * Maybe add the meta box.
	 */
	public function maybe_add_meta_box( $post_type, $post ) {
		$order = $this->confirm_actions( $post );
		if ( ! $order ) {
			return;
		}

		// Get the order edit screen to be able to add the meta box to.
		$wc_screen_id = \wc_get_page_screen_id( 'shop-order' );

		add_meta_box( 'wcpay_mc_order_meta_helper_meta_box', __( 'Multi-Currency Meta Helper', 'woocommerce-payments' ), [ $this, 'display_meta_box' ], $wc_screen_id, 'advanced', 'high' );
	}

	/**
	 * 
	 *
	 * @param \WC_Order $order The order we are working with.
	 *
	 * @return void
	 */
	public function display_meta_box( $order ) {
		// Again, make sure we are actually working with an order.
		$order = wc_get_order( $order );
		if ( ! $order  ) {
			return;
		}

		// $store_currency         = get_woocommerce_currency();
		// $mc_exchange_rate       = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' );
		// $order_default_currency = $order->get_meta( '_wcpay_multi_currency_order_default_currency' );
		// $intent_currency        = $order->get_meta( '_wcpay_intent_currency' );
		// $stripe_exchange_rate   = $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' );
		// $fake_meta              = $order->get_meta( '_fake_meta' );
		$order_currency = $order->get_currency();
		$intent_id      = $order->get_meta( '_intent_id' );
		$payment_method = $order->get_payment_method();
		$is_wcpay       = false !== strpos( $payment_method, 'woocommerce_payments' );

		$store_items = [
			'store_currency'         => [
				'label' => __( 'Store Currency', 'woocommerce-payments' ),
				'value' => get_woocommerce_currency(),
			],
		];

		$order_meta_items = [
			'order_currency'         => [
				'label' => __( 'Order Currency', 'woocommerce-payments' ),
				'value' => $order_currency,
			],
			'order_default_currency' => [
				'label' => __( 'Order Default Currency', 'woocommerce-payments' ),
				'value' => $order->get_meta( '_wcpay_multi_currency_order_default_currency' ),
			],
			'payment_method'         => [
				'label' => __( 'Payment Method ID', 'woocommerce-payments' ),
				'value' => $payment_method,
			],
			'payment_method_title'   => [
				'label' => __( 'Payment Method Title', 'woocommerce-payments' ),
				'value' => $order->get_payment_method_title(),
			],
			'order_default_currency' => [
				'label' => __( 'Order Default Currency', 'woocommerce-payments' ),
				'value' => $order->get_meta( '_wcpay_multi_currency_order_default_currency' ),
			],
			'intent_id'              => [
				'label' => __( 'Intent ID', 'woocommerce-payments' ),
				'value' => $intent_id,
			],
			'intent_currency'        => [
				'label' => __( 'Intent Currency', 'woocommerce-payments' ),
				'value' => $order->get_meta( '_wcpay_intent_currency' ),
			],
			'mc_exchange_rate'       => [
				'label' => __( 'Multi-Currency Exchange Rate', 'woocommerce-payments' ),
				'value' => $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' ),
			],
			'stripe_exchange_rate'   => [
				'label' => __( 'Stripe Exchange Rate', 'woocommerce-payments' ),
				'value' => $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ),
			],
		];

		$charge_items = [
			'charge_exchange_rate'   => [
				'label' => __( 'Charge Exchange Rate', 'woocommerce-payments' ),
				'value' => '',
			],
			'charge_currency'        => [
				'label' => __( 'Charge Currency', 'woocommerce-payments' ),
				'value' => '',
			],
		];

		/**
		 * If the intent isn't a good intent, then a fatal error is thrown. For this reason, we check if it's WCPay.
		 * https://github.com/Automattic/woocommerce-payments/issues/6566
		 */
		if ( ! empty( $intent_id ) && $is_wcpay ) {
			$intent_object        = $this->payments_api_client->get_intent( $intent_id );// . '23' );
			$charge_object        = $intent_object->get_charge();
			$balance_transaction  = $charge_object->get_balance_transaction();
			// $charge_exchange_rate = $balance_transaction[ 'exchange_rate' ];
			// error_log( __FILE__ . ':' . __LINE__ . ":\n" . '$intent_object: ' . print_r( $intent_object, true ) );
			// error_log( __FILE__ . ':' . __LINE__ . ":\n" . '$charge_object: ' . print_r( $charge_object, true ) );

			// Set the Charge Exchange Rate value from the intent itself.
			$charge_items['charge_exchange_rate']['value'] = $balance_transaction[ 'exchange_rate' ];
			$charge_items['charge_currency']['value'] = strtoupper( $charge_object->get_currency() );
		}

		/**
		 * Zero decimal currencies have a different conversion rate value.
		 */
		if ( in_array( strtolower( $order_currency ), WC_Payments_Utils::zero_decimal_currencies(), true ) ) {
			if ( '' !== $charge_items['charge_exchange_rate']['value'] ) {
				$charge_items['charge_exchange_rate']['value'] = $charge_items['charge_exchange_rate']['value'] / 100;
			}

			// if ( '' !== $charge_items['stripe_exchange_rate']['value'] ) {
			// 	$charge_items['stripe_exchange_rate']['value'] = $charge_items['stripe_exchange_rate']['value'] / 100;
			// }
		}

		if ( '' !== $charge_items['charge_exchange_rate']['value'] ) {
			$charge_items['charge_exchange_rate']['value'] = 1 / $charge_items['charge_exchange_rate']['value'];
		}

		if ( '' !== $order_meta_items['stripe_exchange_rate']['value'] ) {
			$order_meta_items['stripe_exchange_rate']['value'] = 1 / $order_meta_items['stripe_exchange_rate']['value'];
		}

		$purpose = __( 'This tool\'s intended use is to add or update the Multi-Currency exchange rate for the order if it is missing.', 'woocommerce-payments' );
		echo '<p style="font-weight:bold; font-size:14px;">' . $purpose . "</p>\n";

		$display_items = [
			__( 'Store items', 'woocommerce-payments' )      => $store_items,
			__( 'Order meta items', 'woocommerce-payments' ) => $order_meta_items,
			__( 'Charge items', 'woocommerce-payments' )     => $charge_items,
		];

		$not_found = __( 'Not found', 'woocommerce-payments' );
		$not_found = '<span style="color:red;">' . $not_found . '</span>';

		foreach ( $display_items as $label => $items ) {
			echo '<p style="font-weight:bold; font-size:14px;">' . $label . "</p>\n<ul>\n";
			foreach ( $items as $item ) {
				$value = ! empty( $item['value'] ) ? $item['value'] : $not_found;
				echo '<li>' . $item['label'] . ': ' . $value . "</li>\n";
			}
			echo "</ul>\n";
		}

		if ( $this->has_errors( $order ) ) {
			foreach ( $this->errors as $error ) {
				echo '<p class="error">' . $error . '</p>';
			}
			$this->clear_errors( $order );
		}

		$new_exchange_rate_description = __( 'Enter a new exchange rate and then click the Update button for the order.', 'woocommerce-payments' );
		$new_exchange_rate_label       = __( 'New exchange rate:', 'woocommerce-payments' );
		?>
			<p style="font-weight:bold; font-size:14px;">
				<?php echo $new_exchange_rate_description; ?>
			</p>
			<p>
				<label for="wcpay_multi_currency_exchange_rate"><?php echo $new_exchange_rate_label; ?><br />
				<input type="text" name="wcpay_multi_currency_exchange_rate" id="wcpay_multi_currency_exchange_rate" />
			</p>
		<?php

		/**
		 * At this point we need to check the currency of the order vs the currency of the store.
		 * If the currencies do not match, we need to make sure the meta is present.
		 * If the meta is not present, we need to get the conversion rate from the transaction.
		 * 
		 * From here, we can display the form with the field with the conversion rate.
		 * We can also display what was found in the meta vs the transaction.
		 * Then we have a submit button.
		 * 
		 * ... I am not sure if we can have a form that overrides the default form, I think it's all included.
		 * 
		 * We need to make sure we add meta to the order, along with a note that the conversion rate meta was updated
		 * - old info
		 * - new info
		 * - who did it
		 * - when it was done
		 */

		 /*
		$intent_id      = $this->order_service->get_intent_id_for_order( $order );
		$charge_id      = $this->order_service->get_charge_id_for_order( $order );
		$meta_box_type  = $this->order_service->get_fraud_meta_box_type_for_order( $order );
		$payment_method = $order->get_payment_method();

		if ( strstr( $payment_method, 'woocommerce_payments_' ) ) {
			$meta_box_type = Fraud_Meta_Box_Type::NOT_CARD;
		} elseif ( 'woocommerce_payments' !== $payment_method ) {
			$meta_box_type = Fraud_Meta_Box_Type::NOT_WCPAY;
		}

		$icons = [
			'green_check_mark' => [
				'url' => plugins_url( 'assets/images/icons/check-green.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Green check mark', 'woocommerce-payments' ),
			],
			'orange_shield'    => [
				'url' => plugins_url( 'assets/images/icons/shield-stroke-orange.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Orange shield outline', 'woocommerce-payments' ),
			],
			'red_shield'       => [
				'url' => plugins_url( 'assets/images/icons/shield-stroke-red.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Red shield outline', 'woocommerce-payments' ),
			],
		];

		$statuses = [
			'blocked'         => __( 'Blocked', 'woocommerce-payments' ),
			'approved'        => __( 'Approved', 'woocommerce-payments' ),
			'held_for_review' => __( 'Held for review', 'woocommerce-payments' ),
			'no_action_taken' => __( 'No action taken', 'woocommerce-payments' ),
		];

		switch ( $meta_box_type ) {
			case Fraud_Meta_Box_Type::ALLOW:
				$description = __( 'The payment for this order passed your risk filtering.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icons['green_check_mark']['url'] ) . '" alt="' . esc_html( $icons['green_check_mark']['alt'] ) . '"> ' . esc_html( $statuses['no_action_taken'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			
		}
		*/
	}

	private function confirm_actions( $order ) {
		// If the feature is not enabled, or if we cannot get the screen ID, exit.
		if ( ! WC_Payments_Features::is_mc_order_meta_helper_enabled() || ! function_exists( '\wc_get_page_screen_id' ) ) {
			return false;
		}

		// If it's actually not an order, or if it's not in a status that accepted a payment, exit.
		$order         = wc_get_order( $order );
		$paid_statuses = array_merge( wc_get_is_paid_statuses(), [ 'refunded' ] );
		if ( ! $order || ! in_array( $order->get_status(), $paid_statuses, true ) ) {
			return false;
		}

		// If the store currency and the order currency match, do not display the box.
		$store_currency = get_woocommerce_currency();
		$order_currency = $order->get_currency();
		if ( $store_currency === $order_currency ) {
			return false;
		}

		return $order;
	}

	private function add_error( $order, $error ) {
		// Refresh the errors, then add the new one.
		$this->get_errors( $order );
		$this->errors[] = $error;

		// Update the errors in the order meta.
		$order->update_meta_data( '_wcpay_multi_currency_order_meta_helper_errors', $this->errors );
		$order->save();
	}

	private function get_errors( \WC_Order $order ) {
		$errors = [];
		if ( $order ) {
			$order_errors = $order->get_meta( '_wcpay_multi_currency_order_meta_helper_errors' );

			if ( is_array( $order_errors ) && 0 < count( $order_errors ) ) {
				$errors = array_merge( $errors, $order_errors );
			}
		}

		if ( 0 < count( $this->errors ) ) {
			$errors = array_merge( $errors, $this->errors );
		}

		$this->errors = array_unique( $errors );

		return $this->errors;
	}

	private function has_errors( $order ) {
		return 0 < count( $this->get_errors( $order ) );
	}

	private function clear_errors( $order ) {
		$this->errors = [];
		$order->delete_meta_data( '_wcpay_multi_currency_order_meta_helper_errors' );
		$order->save();
	}
}
