<?php
/**
 * Class New_UPE_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use Exception;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Method;
use WCPay\Constants\Payment_Type;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\Add_Payment_Method_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Session_Rate_Limiter;
use WC_Order;
use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Explicit_Price_Formatter;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WC_Payment_Token_CC;
use WC_Payments_Token_Service;
use WC_Payment_Token_WCPay_SEPA;
use WC_Payments_Utils;
use WC_Payments_Features;
use WP_User;

/**
 * UPE Payment Gateway that supports the deffered payment intent creation.
 */
class New_UPE_Payment_Gateway extends UPE_Split_Payment_Gateway {

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
	 * Registers all scripts, necessary for the gateway.
	 */
	public function register_scripts() {
		// Register Stripe's JavaScript using the same ID as the Stripe Gateway plugin. This prevents this JS being
		// loaded twice in the event a site has both plugins enabled. We still run the risk of different plugins
		// loading different versions however. If Stripe release a v4 of their JavaScript, we could consider
		// changing the ID to stripe_v4. This would allow older plugins to keep using v3 while we used any new
		// feature in v4. Stripe have allowed loading of 2 different versions of stripe.js in the past (
		// https://stripe.com/docs/stripe-js/elements/migrating).
		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		$script_dependencies = [ 'stripe', 'wc-checkout', 'wp-i18n' ];
		if ( $this->supports( 'tokenization' ) ) {
			$script_dependencies[] = 'woocommerce-tokenization-form';
		}
		WC_Payments::register_script_with_dependencies( 'wcpay-upe-checkout', 'dist/new_upe_checkout', $script_dependencies );
	}

	/**
	 * Displays HTML tags for WC payment gateway radio button content.
	 */
	public function display_gateway_html() {
		?>
			<div class="wcpay-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
		<?php
	}
}
