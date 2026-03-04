interface MyWindow extends Window {
	wcpayExpressCheckoutParams: WCPayExpressCheckoutParams;
}

declare let window: MyWindow;

/**
 * An /incomplete/ representation of the data that is loaded into the frontend for the Express Checkout.
 */
export interface WCPayExpressCheckoutParams {
	ajax_url: string;

	/**
	 * Express Checkout Button style configuration.
	 */
	button: {
		type: string;
		theme: string;
		height: string;
		locale: string;
		branded_type: string;
		radius: number;
	};

	/**
	 * Indicates in which context the button is being displayed.
	 */
	button_context: 'checkout' | 'cart' | 'product' | 'pay_for_order';
	checkout: {
		country_code: string;
		currency_code: string;
		needs_payer_phone: boolean;
		needs_shipping: boolean;
		currency_decimals: number;
	};

	has_subscription?: boolean;

	/**
	 * Indicates whether the page has a Cart or Checkout Block on it.
	 */
	has_block: boolean;

	nonce: {
		add_to_cart: string;
		checkout: string;
		empty_cart: string;
		get_cart_details: string;
		get_selected_product_data: string;
		pay_for_order: string;
		platform_tracker: string;
		shipping: string;
		update_shipping: string;
		tokenized_cart_nonce: string;
		tokenized_cart_session_nonce: string;
		store_api_nonce: string;
	};

	/**
	 * Product specific options.
	 */
	product: {
		needs_shipping: boolean;
		currency: string;
		product_type: string;
		shippingOptions: {
			id: string;
			label: string;
			detail: string;
			amount: number;
		};
	};

	/**
	 * Settings for the user authentication dialog and redirection.
	 */
	login_confirmation: { message: string; redirect_url: string } | false;

	stripe: {
		accountId: string;
		locale: string;
		publishableKey: string;
	};
	total_label: string;
	wc_ajax_url: string;

	/**
	 * The available express checkout methods for the current page context.
	 */
	enabled_methods: Array< 'payment_request' | 'amazon_pay' >;
	flags: {
		isEceUsingConfirmationTokens: boolean;
	};
}

declare global {
	interface Window {
		wcpayExpressCheckoutParams?: WCPayExpressCheckoutParams;
	}
}

export const getExpressCheckoutData = <
	K extends keyof WCPayExpressCheckoutParams
>(
	key: K
) => {
	if ( typeof window.wcpayExpressCheckoutParams !== 'undefined' ) {
		return window.wcpayExpressCheckoutParams[ key ] ?? null;
	}

	if ( typeof window.wc?.wcSettings !== 'undefined' ) {
		return window.wc.wcSettings.getSetting( 'ece_data' )?.[ key ] ?? null;
	}

	return null;
};
