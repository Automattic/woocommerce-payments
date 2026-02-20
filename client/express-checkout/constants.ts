/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export type ExpressPaymentMethodKey = 'applePay' | 'googlePay' | 'amazonPay';
export type ExpressPaymentMethodConfigKey =
	| 'apple_pay'
	| 'google_pay'
	| 'amazon_pay';

export interface ExpressPaymentMethodConfig {
	/** snake_case identifier (e.g. 'apple_pay') */
	key: ExpressPaymentMethodConfigKey;
	/** Sent to server as the express payment type */
	expressPaymentType: string;
	/** Stripe payment method types for Elements initialization */
	paymentMethodTypes: string[];
	/** WC gateway ID for registration */
	gatewayId: string;
	/** Display title (for block registration) */
	title: string;
	/** Display description */
	description: string;
	/** Aria label for accessibility */
	ariaLabel: string;
	/** Fallback title when config title is unavailable */
	fallbackTitle: string;
}

// eslint-disable-next-line @typescript-eslint/naming-convention
export const EXPRESS_PAYMENT_METHODS: Record<
	ExpressPaymentMethodKey,
	ExpressPaymentMethodConfig
> = {
	applePay: {
		key: 'apple_pay',
		expressPaymentType: 'apple_pay',
		paymentMethodTypes: [ 'card' ],
		gatewayId: 'woocommerce_payments_apple_pay',
		title: 'WooPayments - Apple Pay',
		description: __(
			"An easy, secure way to pay that's accepted on millions of stores.",
			'woocommerce-payments'
		),
		ariaLabel: 'Apple Pay',
		fallbackTitle: 'Apple Pay',
	},
	googlePay: {
		key: 'google_pay',
		expressPaymentType: 'google_pay',
		paymentMethodTypes: [ 'card' ],
		gatewayId: 'woocommerce_payments_google_pay',
		title: 'WooPayments - Google Pay',
		description: __(
			'Simplify checkout with fewer steps to pay.',
			'woocommerce-payments'
		),
		ariaLabel: 'Google Pay',
		fallbackTitle: 'Google Pay',
	},
	amazonPay: {
		key: 'amazon_pay',
		expressPaymentType: 'amazon_pay',
		paymentMethodTypes: [ 'amazon_pay' ],
		gatewayId: 'woocommerce_payments_amazon_pay',
		title: 'WooPayments - Amazon Pay',
		description: __(
			'Pay with your Amazon account.',
			'woocommerce-payments'
		),
		ariaLabel: 'Amazon Pay',
		fallbackTitle: 'Amazon Pay',
	},
};

/**
 * Look up an express payment method config by its snake_case key (e.g. 'apple_pay').
 * Returns both the config and the camelCase key, so callers don't need manual conversion.
 */
export function getExpressMethodByConfigKey(
	configKey: string
):
	| { camelKey: ExpressPaymentMethodKey; config: ExpressPaymentMethodConfig }
	| undefined {
	for ( const [ camelKey, config ] of Object.entries(
		EXPRESS_PAYMENT_METHODS
	) ) {
		if ( config.key === configKey ) {
			return {
				camelKey: camelKey as ExpressPaymentMethodKey,
				config,
			};
		}
	}
	return undefined;
}
