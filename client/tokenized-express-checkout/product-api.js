/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

export default class ExpressCheckoutProductApi {
	productId;

	/**
	 * Creates an instance of class to query for product data.
	 *
	 * @param {string} productId The product ID,
	 */
	constructor( { productId } ) {
		this.productId = productId;
	}

	/**
	 * Returns the product data object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/products.md#single-product-by-id
	 *
	 * @return {Promise} Product response object.
	 */
	async getProduct() {
		return await apiFetch( {
			method: 'GET',
			path: `/wc/store/v1/products/${ this.productId }`,
		} );
	}
}
