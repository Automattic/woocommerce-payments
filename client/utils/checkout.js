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
