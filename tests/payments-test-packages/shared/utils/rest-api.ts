/* eslint-disable no-console */
/**
 * External dependencies
 */
import qit from '../qit-helpers/index.js';

/**
 * Internal dependencies
 */
import { config, CustomerAddress } from '../config/default';

export type CustomerType = typeof config.users.customer;

/**
 * RestAPI utility class that uses WP-CLI commands via QIT helpers.
 *
 * In the QIT containerized environment, we use WP-CLI directly instead of
 * HTTP requests for database operations like customer management.
 */
class RestAPI {
	/**
	 * Deletes a customer account by their email address if the user exists.
	 *
	 * @param {string} emailAddress Customer user account email address.
	 * @return {Promise<void>}
	 */
	async deleteCustomerByEmailAddress(
		emailAddress: string
	): Promise< void > {
		try {
			// Get user by email using WP-CLI
			const result = await qit.wp(
				`user get ${ emailAddress } --field=ID`,
				true
			);

			const userId = result.stdout.trim();
			if ( userId && ! isNaN( Number( userId ) ) ) {
				// Delete the user with reassign to admin (user 1)
				await qit.wp( `user delete ${ userId } --yes --reassign=1` );
				console.log(
					`Deleted customer with email: ${ emailAddress } (ID: ${ userId })`
				);
			}
		} catch ( error ) {
			// User doesn't exist, which is fine
			console.log(
				`Customer with email ${ emailAddress } not found, skipping deletion.`
			);
		}
	}

	/**
	 * Creates a new customer with the given data.
	 *
	 * @param {CustomerType}    customerData    Customer credentials.
	 * @param {CustomerAddress} billingAddress  Billing address data.
	 * @param {CustomerAddress} shippingAddress Shipping address data.
	 * @return {Promise<number>} The created customer's ID.
	 */
	async createCustomer(
		customerData: CustomerType,
		billingAddress: CustomerAddress,
		shippingAddress: CustomerAddress
	): Promise< number > {
		// Create user with WP-CLI
		const createResult = await qit.wp(
			`user create ${ customerData.username } ${ customerData.email } --user_pass=${ customerData.password } --role=customer --porcelain`
		);

		const userId = parseInt( createResult.stdout.trim(), 10 );

		if ( isNaN( userId ) ) {
			throw new Error(
				`Failed to create customer. Output: ${ createResult.stdout }`
			);
		}

		// Update user meta for names
		await qit.wp(
			`user update ${ userId } --first_name="${ billingAddress.firstname }" --last_name="${ billingAddress.lastname }"`
		);

		// Set billing address meta using WP-CLI
		const billingMeta = {
			billing_first_name: billingAddress.firstname,
			billing_last_name: billingAddress.lastname,
			billing_company: billingAddress.company || '',
			billing_address_1: billingAddress.addressfirstline,
			billing_address_2: billingAddress.addresssecondline || '',
			billing_city: billingAddress.city,
			billing_state: billingAddress.state || '',
			billing_postcode: billingAddress.postcode,
			billing_country: billingAddress.country_code,
			billing_email: billingAddress.email,
			billing_phone: billingAddress.phone || '',
		};

		for ( const [ key, value ] of Object.entries( billingMeta ) ) {
			await qit.wp(
				`user meta update ${ userId } ${ key } "${ value }"`,
				true
			);
		}

		// Set shipping address meta using WP-CLI
		const shippingMeta = {
			shipping_first_name: shippingAddress.firstname,
			shipping_last_name: shippingAddress.lastname,
			shipping_company: shippingAddress.company || '',
			shipping_address_1: shippingAddress.addressfirstline,
			shipping_address_2: shippingAddress.addresssecondline || '',
			shipping_city: shippingAddress.city,
			shipping_state: shippingAddress.state || '',
			shipping_postcode: shippingAddress.postcode,
			shipping_country: shippingAddress.country_code,
		};

		for ( const [ key, value ] of Object.entries( shippingMeta ) ) {
			await qit.wp(
				`user meta update ${ userId } ${ key } "${ value }"`,
				true
			);
		}

		console.log(
			`Created customer: ${ customerData.username } (ID: ${ userId })`
		);

		return userId;
	}

	/**
	 * Deletes an existing customer and recreates them with fresh data.
	 *
	 * @param {CustomerType}    customerData Customer credentials.
	 * @param {CustomerAddress} billing      Billing address data.
	 * @param {CustomerAddress} shipping     Shipping address data.
	 * @return {Promise<number>} The recreated customer's ID.
	 */
	async recreateCustomer(
		customerData: CustomerType,
		billing: CustomerAddress,
		shipping: CustomerAddress
	): Promise< number > {
		await this.deleteCustomerByEmailAddress( customerData.email );
		// Small delay to ensure deletion is complete
		await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
		return this.createCustomer( customerData, billing, shipping );
	}

	/**
	 * Creates a new order with a simple product.
	 *
	 * @return {Promise<string>} The created order ID.
	 */
	async createOrder(): Promise< string > {
		// Get a product ID using WP-CLI
		const productResult = await qit.wp(
			`wc product list --search="${ config.products.simple.name }" --field=id --format=csv`,
			true
		);

		const productId = productResult.stdout.trim().split( '\n' )[ 1 ]; // Skip header

		if ( ! productId || isNaN( Number( productId ) ) ) {
			throw new Error(
				`Product "${ config.products.simple.name }" not found.`
			);
		}

		// Create order with the product using WP-CLI
		const orderResult = await qit.wp(
			`wc shop_order create --line_items='[{"product_id":${ productId },"quantity":1}]' --porcelain`,
			true
		);

		const orderId = orderResult.stdout.trim();

		if ( ! orderId || isNaN( Number( orderId ) ) ) {
			throw new Error(
				`Failed to create order. Output: ${ orderResult.stdout }`
			);
		}

		console.log( `Created order ID: ${ orderId }` );

		return orderId;
	}
}

export default RestAPI;
