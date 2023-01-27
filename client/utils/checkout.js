/* global wcpay_config, wcpay_upe_config, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	const config =
		'undefined' !== typeof wcpay_config
			? wcpay_config
			: wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config[ name ] || null;
};

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getUPEConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	const config =
		'undefined' !== typeof wcpay_upe_config
			? wcpay_upe_config
			: wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config[ name ] || null;
};

/**
 * Validate email address.
 * Borrowed from WooCommerce checkout.js with a slight tweak to add `{2,}` to the end and make the TLD at least 2 characters.
 *
 * @param {string} value Email input.
 * @return {boolean} Is email input valid address.
 */
export const validateEmail = ( value ) => {
	/* eslint-disable */
	const pattern = new RegExp(
		/^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[0-9a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]){2,}\.?$/i
	);
	/* eslint-enable */
	return pattern.test( value );
};

/**
 * Class interface for preloader spinner for WooPay/Link.
 */
export class Spinner {
	/**
	 * Constructor for spinner class.
	 *
	 * @param {Object} emailInput Email input DOM element.
	 */
	constructor( emailInput ) {
		this.emailInput = emailInput;
		this.parentDiv = emailInput.parentNode;
		this.className = 'wc-block-components-spinner';
	}

	/**
	 * Find spinner, if present on page.
	 *
	 * @return {Object} Preloader DOM object.
	 */
	getSpinner() {
		return this.parentDiv.querySelector( `div.${ this.className }` );
	}

	/**
	 * Create preloading element on email input.
	 */
	createSpinner() {
		const spinner = document.createElement( 'div' );
		spinner.classList.add( this.className );
		this.parentDiv.insertBefore( spinner, this.emailInput );
	}

	/**
	 * Show spinner on page or add task to preloading queue.
	 */
	show() {
		const spinner = this.getSpinner();
		if ( ! spinner ) {
			this.createSpinner();
		}
	}

	/**
	 * Hide spinner from page or remove task from preloading queue.
	 */
	remove() {
		const spinner = this.getSpinner();
		if ( ! spinner ) {
			return;
		}
		spinner.remove();
	}
}
