/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export interface PaymentMethodServerDefinition {
	id: string;
	label: string;
	description: string;
	settings_icon_url: string;
	currencies: string[];
	stripe_key: string;
	allows_manual_capture: boolean;
	allows_pay_later: boolean;
	accepts_only_domestic_payment: boolean;
}

export interface PaymentMethodMapEntry extends PaymentMethodServerDefinition {
	icon: ReactImgFuncComponent;
}
