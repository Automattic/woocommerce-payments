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

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls OrderMetaHelper functionality.
 */
class OrderMetaHelper {
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
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
		$this->add_actions();
	}

	/**
	 * Adds the button to toggle the tool on and off under WooCommerce > Status > Tools.
	 *
	 * @param array $tools The current array of tools.
	 *
	 * @return array The modified tools array.
	 */
	public function add_tool_button( $tools ) {
		/**
		 * We are not able to look at the current status of the tool to dynamically change these labels. The tool runs after the labels are created.
		 */
		$tools['wcpay_mc_meta_helper_toggle'] = [
			'name'     => __( 'Enable/Disable Multi-Currency Meta Helper', 'woocommerce-payments' ),
			'button'   => __( 'Enable/Disable', 'woocommerce-payments' ),
			'desc'     => __( 'This tool can be used to fix meta data on orders related to Multi-Currency conversion rates.', 'woocommerce-payments' ),
			'callback' => [ $this, 'toggle_helper_option' ],
		];

		return $tools;
	}

	/**
	 * Toggles the feature on and off via database option.
	 *
	 * @return string Whether the feature has been enabled or disabled.
	 */
	public function toggle_helper_option() {
		if ( WC_Payments_Features::is_mc_order_meta_helper_enabled() ) {
			update_option( WC_Payments_Features::MC_ORDER_META_HELPER_FLAG_NAME, 0 );
			return __( 'Multi-Currency Meta Helper has been disabled', 'woocommerce-payments' );
		}

		update_option( WC_Payments_Features::MC_ORDER_META_HELPER_FLAG_NAME, 1 );
		return __( 'Multi-Currency Meta Helper has been enabled', 'woocommerce-payments' );
	}

	/**
	 * Outputs and then clears our errors if there are any.
	 *
	 * @return void
	 */
	public function maybe_output_errors() {
		if ( $this->has_errors() ) {
			foreach ( $this->errors as $error ) {

				?>
				<div class="error notice">
					<p><strong>Error: </strong><?php echo esc_html( $error ); ?></p>
				</div>
				<?php
			}
			$this->clear_errors();
		}
	}

	/**
	 * Updates the exchange rate meta data.
	 *
	 * @param int $order_id The order we are working with.
	 *
	 * @return void
	 */
	public function maybe_update_exchange_rate( $order_id ) {
		// Verify nonce.
		$nonce_value = ! empty( $_POST['wcpay_multi_currency_exchange_rate_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wcpay_multi_currency_exchange_rate_nonce'] ) ) : false;
		if ( false === $nonce_value || ! wp_verify_nonce( $nonce_value, 'wcpay_multi_currency_exchange_rate_nonce_' . $order_id ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Confirm our order is valid, should be worked on, and that a new exchange rate has been passed.
		$order = $this->confirm_actions( $order_id );
		if ( ! $order || empty( $_POST['wcpay_multi_currency_exchange_rate'] ) ) {
			return;
		}

		// Clean the data, confirm the rate is a number, and if it isn't, add error and exit.
		$new_exchange_rate = sanitize_text_field( wp_unslash( $_POST['wcpay_multi_currency_exchange_rate'] ) );
		if ( ! is_numeric( $new_exchange_rate ) ) {
			$this->add_error( 'Exchange rate value not numeric: ' . $new_exchange_rate );
			return;
		}

		// Add the exchange rates to the log.
		$old_exchange_rate = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' );
		$old_exchange_rate = ! empty( $old_exchange_rate ) ? $old_exchange_rate : __( 'Not found', 'woocommerce-payments' );
		$exchange_rate_log = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate_log' );
		$exchange_rate_log = ( is_array( $exchange_rate_log ) ) ? $exchange_rate_log : [];
		$current_time      = time();

		$exchange_rate_log[ $current_time ] = [
			'user_id'  => get_current_user_id(),
			'old_rate' => $old_exchange_rate,
			'new_rate' => $new_exchange_rate,
		];

		// Add an order note stating what was updated.
		$note = sprintf(
			/* translators: %1 Old exchange rate, or 'Not found' string, %2 new exchange rate */
			__( 'The exchange rate has been updated:<br>From: %1$s<br>To: %2$s', 'woocommerce-payments' ),
			$old_exchange_rate,
			$new_exchange_rate
		);
		$order->add_order_note( $note, 0, true );

		// Update the exchange rate and the log on the order.
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', $new_exchange_rate );
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate_log', $exchange_rate_log );
		$order->save_meta_data();
	}

	/**
	 * Maybe add the meta box.
	 *
	 * @param string $post_type The type of post being viewed.
	 * @param object $post      The post object for the post being viewed.
	 *
	 * @return void
	 */
	public function maybe_add_meta_box( $post_type, $post ) {
		// Confirm we should be working on the order, if not, exit.
		$order = $this->confirm_actions( $post );
		if ( ! $order || ! function_exists( '\wc_get_page_screen_id' ) ) {
			return;
		}

		// Get the order edit screen to be able to add the meta box to.
		$wc_screen_id = \wc_get_page_screen_id( 'shop-order' );

		add_meta_box( 'wcpay_mc_order_meta_helper_meta_box', __( 'Multi-Currency Meta Helper', 'woocommerce-payments' ), [ $this, 'display_meta_box_content' ], $wc_screen_id, 'advanced', 'high' );
	}

	/**
	 * Displays the content of the meta box.
	 *
	 * @param \WC_Order $order The order we are working with.
	 *
	 * @return void
	 */
	public function display_meta_box_content( $order ) {
		// Again, make sure we are actually working with an order.
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return;
		}

		// Start getting all of the items we need.
		$order_currency = strtoupper( $order->get_currency() );
		$intent_id      = $order->get_meta( '_intent_id' );
		$payment_method = $order->get_payment_method();
		$is_wcpay       = false !== strpos( $payment_method, 'woocommerce_payments' );

		// Define the store items.
		$store_items = [
			'store_currency' => [
				'label' => __( 'Store Currency', 'woocommerce-payments' ),
				'value' => strtoupper( get_woocommerce_currency() ),
			],
		];

		// Define the order meta items.
		$order_meta_items = [
			'order_currency'         => [
				'label' => __( 'Order Currency', 'woocommerce-payments' ),
				'value' => $order_currency,
			],
			'order_default_currency' => [
				'label' => __( 'Order Default Currency', 'woocommerce-payments' ),
				'value' => strtoupper( $order->get_meta( '_wcpay_multi_currency_order_default_currency' ) ),
			],
			'payment_method'         => [
				'label' => __( 'Payment Method ID', 'woocommerce-payments' ),
				'value' => $payment_method,
			],
			'payment_method_title'   => [
				'label' => __( 'Payment Method Title', 'woocommerce-payments' ),
				'value' => $order->get_payment_method_title(),
			],
			'intent_id'              => [
				'label' => __( 'Intent ID', 'woocommerce-payments' ),
				'value' => $intent_id,
			],
			'intent_currency'        => [
				'label' => __( 'Intent Currency', 'woocommerce-payments' ),
				'value' => strtoupper( $order->get_meta( '_wcpay_intent_currency' ) ),
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

		// Define the charge (intent) items.
		$charge_items = [
			'charge_exchange_rate' => [
				'label' => __( 'Charge Exchange Rate', 'woocommerce-payments' ),
				'value' => '',
			],
			'charge_currency'      => [
				'label' => __( 'Charge Currency', 'woocommerce-payments' ),
				'value' => '',
			],
		];

		/**
		 * If the intent isn't a good intent, then a fatal error is thrown. For this reason, we check if it's WCPay.
		 * https://github.com/Automattic/woocommerce-payments/issues/6566
		 */
		if ( ! empty( $intent_id ) && $is_wcpay ) {
			// Get the intent, charge, and then the transaction from the charge.
			$intent_object       = $this->payments_api_client->get_intent( $intent_id );
			$charge_object       = $intent_object->get_charge();
			$balance_transaction = $charge_object->get_balance_transaction();

			// Set the Charge Exchange Rate value from the intent itself.
			$charge_items['charge_exchange_rate']['value'] = $balance_transaction['exchange_rate'];
			$charge_items['charge_currency']['value']      = strtoupper( $charge_object->get_currency() );
		}

		/**
		 * Zero decimal currencies have a different conversion rate value.
		 */
		if ( in_array( strtolower( $order_currency ), WC_Payments_Utils::zero_decimal_currencies(), true ) ) {
			if ( '' !== $charge_items['charge_exchange_rate']['value'] ) {
				$charge_items['charge_exchange_rate']['value'] = $charge_items['charge_exchange_rate']['value'] / 100;
			}
		}

		// Convert the Stripe exchange amounts to what we use for Multi-Currency.
		if ( '' !== $charge_items['charge_exchange_rate']['value'] ) {
			$charge_items['charge_exchange_rate']['value'] = 1 / $charge_items['charge_exchange_rate']['value'];
		}
		if ( '' !== $order_meta_items['stripe_exchange_rate']['value'] ) {
			$order_meta_items['stripe_exchange_rate']['value'] = 1 / $order_meta_items['stripe_exchange_rate']['value'];
		}

		// Let's see if we can get a suggested rate.
		$suggested = false;
		if ( empty( $order_meta_items['mc_exchange_rate']['value'] ) ) {
			if ( ! empty( $charge_items['charge_exchange_rate']['value'] ) ) {
				$suggested = $charge_items['charge_exchange_rate']['value'];
			}
			if ( ! empty( $order_meta_items['stripe_exchange_rate']['value'] ) ) {
				$suggested = $order_meta_items['stripe_exchange_rate']['value'];
			}
		}

		// Define our labels.
		$purpose                       = __( 'This tool\'s intended use is to add or update the Multi-Currency exchange rate for the order if it is missing.', 'woocommerce-payments' );
		$new_exchange_rate_description = __( 'Enter a new exchange rate and then click the Update button for the order.', 'woocommerce-payments' );
		$suggested_label               = __( 'Suggested exchange rate: ', 'woocommerce-payments' );
		$new_exchange_rate_label       = __( 'New exchange rate:', 'woocommerce-payments' );
		$nonce_value                   = wp_create_nonce( 'wcpay_multi_currency_exchange_rate_nonce_' . $order->get_id() );

		// Display the form itself.
		?>
			<p style="font-weight:bold; font-size:14px;">
				<?php echo esc_html( $purpose ); ?>
			</p>
			<p style="font-weight:bold; font-size:14px;">
				<?php echo esc_html( $new_exchange_rate_description ); ?>
			</p>
			<?php if ( false !== $suggested ) { ?>
				<p style="font-weight:bold; font-size:14px;">
				<?php echo esc_html( $suggested_label . $suggested ); ?>
				</p>
			<?php } ?>
			<p>
				<label for="wcpay_multi_currency_exchange_rate"><?php echo esc_html( $new_exchange_rate_label ); ?><br />
				<input type="text" name="wcpay_multi_currency_exchange_rate" id="wcpay_multi_currency_exchange_rate" />
				<input type="hidden" name="wcpay_multi_currency_exchange_rate_nonce" id="wcpay_multi_currency_exchange_rate_nonce" value="<?php echo esc_html( $nonce_value ); ?>" />
			</p>
			<hr>
		<?php

		$display_items = [
			__( 'Store items', 'woocommerce-payments' )  => $store_items,
			__( 'Order meta items', 'woocommerce-payments' ) => $order_meta_items,
			__( 'Charge items', 'woocommerce-payments' ) => $charge_items,
		];

		$not_found = __( 'Not found', 'woocommerce-payments' );

		// Iterate through our display items to display all available information in a table.
		echo "<table>\n";
		foreach ( $display_items as $label => $items ) {
			echo '<tr><td colspan="2" style="font-weight:bold; font-size:14px; padding:5px; background-color: #f2f2f2;">' . esc_html( $label ) . "</td></tr>\n";
			foreach ( $items as $item ) {
				echo '<tr><td style="padding: 5px;">' . esc_html( $item['label'] ) . ': </td><td style="padding: 5px;">';
				if ( ! empty( $item['value'] ) ) {
					echo esc_html( $item['value'] );
				} else {
					echo '<span style="color:red;">' . esc_html( $not_found ) . '</span>';
				}
				echo "</td></tr>\n";
			}
		}
		echo "</table>\n";
	}

	/**
	 * Adds our actions and hooks.
	 *
	 * @return void
	 */
	private function add_actions() {
		add_action( 'add_meta_boxes', [ $this, 'maybe_add_meta_box' ], 10, 2 );
		add_action( 'save_post', [ $this, 'maybe_update_exchange_rate' ] );
		add_filter( 'woocommerce_debug_tools', [ $this, 'add_tool_button' ] );
		add_action( 'admin_notices', [ $this, 'maybe_output_errors' ] );
	}

	/**
	 * Confirms that we should be working on this order.
	 *
	 * @param int|\WC_Order $order The order we're working with.
	 *
	 * @return bool|\WC_Order|\WC_Order_Refund Returns false or the order we're working with.
	 */
	private function confirm_actions( $order ) {
		// If the feature is not enabled, exit.
		if ( ! WC_Payments_Features::is_mc_order_meta_helper_enabled() ) {
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

	/**
	 * Adds an error to the stack for us.
	 *
	 * @param string $error The error to add to the stack.
	 *
	 * @return void
	 */
	private function add_error( $error ) {
		// Refresh the errors, then add the new one.
		$this->get_errors();
		$this->errors[] = $error;

		// Update the errors option.
		update_option( '_wcpay_multi_currency_order_meta_helper_errors', $this->errors );
	}

	/**
	 * Gets the errors from the options table.
	 *
	 * @return array The array of errors.
	 */
	private function get_errors() {
		// Get any errors from the database.
		$errors = array_filter( (array) get_option( '_wcpay_multi_currency_order_meta_helper_errors' ) );

		// If we have any errors currently in our object, add those.
		if ( 0 < count( $this->errors ) ) {
			$errors = array_merge( $errors, $this->errors );
		}

		// Add all the errors to the object, and return them.
		$this->errors = array_unique( $errors );

		return $this->errors;
	}

	/**
	 * Checks if we have any errors.
	 *
	 * @return bool
	 */
	private function has_errors() {
		return 0 < count( $this->get_errors() );
	}

	/**
	 * Removes the errors from the object and deletes the database option.
	 *
	 * @return void
	 */
	private function clear_errors() {
		$this->errors = [];
		delete_option( '_wcpay_multi_currency_order_meta_helper_errors' );
	}
}
