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
		stripeId: 'affirm',
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
		icons: {
			default: {
				path: '/assets/images/payment-methods/affirm-logo.svg',
			},
			dark: {
				path: '/assets/images/payment-methods/affirm-logo-dark.svg',
			},
		},
	},
};
