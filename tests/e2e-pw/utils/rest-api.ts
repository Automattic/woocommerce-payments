/**
 * External dependencies
 */
import { HTTPClientFactory } from '@woocommerce/api';

/**
 * Internal dependencies
 */
import { config } from '../config/default';

const userEndpoint = '/wp/v2/users';
const ordersEndpoint = '/wc/v3/orders';

class RestAPI {
	private baseUrl: string;

	constructor( baseUrl: string ) {
		if ( ! baseUrl ) {
			throw new Error( 'Base URL is required.' );
		}
		this.baseUrl = baseUrl;
	}

	private getAdminClient() {
		return HTTPClientFactory.build( this.baseUrl )
			.withBasicAuth(
				config.users.admin.username,
				config.users.admin.password
			)
			.create();
	}

	/**
	 * Deletes a customer account by their email address if the user exists.
	 *
	 * Copied from https://github.com/woocommerce/woocommerce/blob/trunk/packages/js/e2e-utils/src/flows/with-rest-api.js#L374
	 *
	 * @param {string} emailAddress Customer user account email address.
	 * @return {Promise<void>}
	 */
	async deleteCustomerByEmailAddress(
		emailAddress: string
	): Promise< void > {
		const client = this.getAdminClient();

		const query = {
			search: emailAddress,
			context: 'edit',
		};
		const customers = await client.get( userEndpoint, query );

		if ( customers.data && customers.data.length ) {
			for ( let c = 0; c < customers.data.length; c++ ) {
				const deleteUser = {
					id: customers.data[ c ].id,
					force: true,
					reassign: 1,
				};

				await client.delete(
					`${ userEndpoint }/${ deleteUser.id }`,
					deleteUser
				);
			}
		}
	}

	async createOrder(): Promise< string > {
		const client = this.getAdminClient();

		const order = await client.post( ordersEndpoint, {
			line_items: [
				{
					product_id: 16,
					quantity: 1,
				},
			],
		} );

		return `${ order.data.id }`;
	}
}

export default RestAPI;
