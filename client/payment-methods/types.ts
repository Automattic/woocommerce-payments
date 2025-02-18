/**
 * This file is auto-generated. Do not edit manually.
 */

export interface PaymentMethodIcon {
	path: string;
	dark_path?: string;
}

export interface PaymentMethodIcons {
	default: PaymentMethodIcon;
	dark?: PaymentMethodIcon;
}

export interface PaymentMethodDefinition {
	id: string;
	stripeId: string;
	title: string;
	description: string;
	capabilities: string[];
	currencies: string[];
	countries: string[];
	allowsManualCapture: boolean;
	allowsPayLater: boolean;
	acceptsOnlyDomesticPayment: boolean;
	settingsIcon: string;
	icons: PaymentMethodIcons;
}

export type PaymentMethodConfigurations = {
	[ key: string ]: PaymentMethodDefinition;
};

export const PaymentMethodCapability = {
	TOKENIZATION: 'tokenization' as const,
	REFUNDS: 'refunds' as const,
	CAPTURE_LATER: 'capture_later' as const,
	MULTI_CURRENCY: 'multi_currency' as const,
	BUY_NOW_PAY_LATER: 'buy_now_pay_later' as const,
	DOMESTIC_TRANSACTIONS_ONLY: 'domestic_transactions_only' as const,
} as const;

export type PaymentMethodCapabilityType = typeof PaymentMethodCapability[ keyof typeof PaymentMethodCapability ];

export const PaymentMethodDefinitions: PaymentMethodConfigurations = {
	affirm: {
		id: 'affirm',
		stripeId: 'affirm_payments',
		title: 'Affirm',
		description: 'Allow customers to pay over time with Affirm.',
		capabilities: [
			'refunds',
			'buy_now_pay_later',
			'multi_currency',
			'domestic_transactions_only',
		],
		currencies: [ 'USD', 'CAD' ],
		countries: [ 'US', 'CA' ],
		allowsManualCapture: false,
		allowsPayLater: true,
		acceptsOnlyDomesticPayment: true,
		settingsIcon: 'assets/images/payment-methods/affirm-badge.svg',
		icons: {
			default: {
				path: 'assets/images/payment-methods/affirm-logo.svg',
			},
			dark: {
				path: 'assets/images/payment-methods/affirm-logo-dark.svg',
			},
		},
	},
	afterpay_clearpay: {
		id: 'afterpay_clearpay',
		stripeId: 'afterpay_clearpay_payments',
		title: 'Afterpay',
		description: 'Allow customers to pay over time with Afterpay.',
		capabilities: [
			'refunds',
			'buy_now_pay_later',
			'multi_currency',
			'domestic_transactions_only',
		],
		currencies: [ 'USD', 'CAD', 'AUD', 'NZD', 'GBP' ],
		countries: [ 'US', 'CA', 'AU', 'NZ', 'GB' ],
		allowsManualCapture: false,
		allowsPayLater: true,
		acceptsOnlyDomesticPayment: true,
		settingsIcon: 'assets/images/payment-methods/afterpay-logo.svg',
		icons: {
			default: {
				path: 'assets/images/payment-methods/afterpay-badge.svg',
			},
			dark: {
				path: 'assets/images/payment-methods/afterpay-badge.svg',
			},
		},
	},
	klarna: {
		id: 'klarna',
		stripeId: 'klarna_payments',
		title: 'Klarna',
		description: 'Allow customers to pay over time or pay now with Klarna.',
		capabilities: [
			'refunds',
			'buy_now_pay_later',
			'multi_currency',
			'domestic_transactions_only',
		],
		currencies: [ 'USD', 'GBP', 'EUR', 'DKK', 'NOK', 'SEK' ],
		countries: [
			'US',
			'GB',
			'AT',
			'DE',
			'NL',
			'BE',
			'ES',
			'IT',
			'IE',
			'DK',
			'FI',
			'NO',
			'SE',
			'FR',
		],
		allowsManualCapture: false,
		allowsPayLater: true,
		acceptsOnlyDomesticPayment: true,
		settingsIcon: 'assets/images/payment-methods/klarna.svg',
		icons: {
			default: {
				path: 'assets/images/payment-methods/klarna-pill.svg',
			},
			dark: {
				path: 'assets/images/payment-methods/klarna-pill.svg',
			},
		},
	},
};
