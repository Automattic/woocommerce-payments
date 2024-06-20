export * from './normalize';

/**
 * An /incomplete/ representation of the data that is loaded into the frontend for the Express Checkout.
 */
interface WCPayExpressCheckoutParams {
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
	};

	/**
	 * Indicaters whether the page has a Cart or Checkout Block on it.
	 */
	has_block: boolean;

	/**
	 * True if we're on the checkout page.
	 */
	is_checkout_page: boolean;

	/**
	 * True if we're on a product page.
	 */
	is_product_page: boolean;

	/**
	 * True if we're on the pay for order page.
	 */
	is_pay_for_order_page: boolean;
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
	};

	/**
	 * Product specific options.
	 */
	product: {
		needs_shipping: boolean;
		currency: string;
		shippingOptions: {
			id: string;
			label: string;
			detail: string;
			amount: number;
		};
	};
	stripe: {
		accountId: string;
		locale: string;
		publishableKey: string;
	};
	total_label: string;
	wc_ajax_url: string;
}

declare global {
	interface Window {
		wcpayExpressCheckoutParams?: WCPayExpressCheckoutParams;
	}
}

export const getExpressCheckoutData = (
	key: keyof WCPayExpressCheckoutParams
) => {
	if ( window.wcpayExpressCheckoutParams ) {
		return window.wcpayExpressCheckoutParams?.[ key ];
	}

	return null;
};

/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param notice Error notice.
 * @return Error messages.
 */
export const getErrorMessageFromNotice = ( notice: string ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};
