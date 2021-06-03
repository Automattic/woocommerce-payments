<?php
/**
 * Class CC_Payment_Gateway
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;

/**
 * Credit Card Payment method.
 * Right now behaves exactly like WC_Payment_Gateway_WCPay for max compatibility.
 */
class CC_Payment_Gateway extends WC_Payment_Gateway_WCPay {
	/**
	 * Digital Wallets Constructor same parameters as WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payments_Customer_Service $customer_service, WC_Payments_Token_Service $token_service, WC_Payments_Action_Scheduler_Service $action_scheduler_service ) {
		parent::__construct( $payments_api_client, $account, $customer_service, $token_service, $action_scheduler_service );

		add_action(
			'woocommerce_payment_gateways_setting_column_logos',
			function( $gateway ) {
				if ( 'woocommerce_payments' !== $gateway->id ) {
					echo '<td class="logo"></td>';
					return;
				}

				$icons = [
					'visa',
					'mastercard',
					'amex',
					'apple-pay',
					'google-pay',
				];

				echo '<td class="logo">';
				?>
					<div>
						<?php foreach ( $icons as $icon ) : ?>
							<span class="payment-method__icon payment-method__brand payment-method__brand--<?php echo esc_attr( $icon ); ?>"/></span>
						<?php endforeach; ?>
					</div>
					<?php
					echo '</td>';
			}
		);
	}
}
