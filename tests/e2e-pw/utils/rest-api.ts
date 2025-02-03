/**
 * External dependencies
 */
import { HTTPClientFactory } from '@woocommerce/api';

/**
 * Internal dependencies
 */
import { config } from '../config/default';

const userEndpoint = '/wc/v3/customers';
const ordersEndpoint = '/wc/v3/orders';
const widgetEndpoint = '/wp/v2/widgets';

export type CustomerType = typeof config.users.customer;
export type AddressType = Omit<
	typeof config.addresses.customer.billing,
	'state'
> & { state?: string };

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
			role: 'all',
		};
		const customers = await client.get( userEndpoint, query );
		if ( customers.data && customers.data.length ) {
			for ( let c = 0; c < customers.data.length; c++ ) {
				const deleteUserPayload = {
					force: true,
					reassign: 0,
				};

				await client.delete(
					`${ userEndpoint }/${ customers.data[ c ].id }`,
					deleteUserPayload
				);
			}
		}
	}

	async deleteWidgets(
		widgetArea: string,
		widgetName: string,
		blockFilter?: string
	): Promise< void > {
		const client = this.getAdminClient();

		const query = {
			sidebar: widgetArea,
			context: 'edit',
		};
		const widgets = await client.get( widgetEndpoint, query );

		if ( widgets.data && widgets.data.length ) {
			for ( let c = 0; c < widgets.data.length; c++ ) {
				if ( widgets.data[ c ].id_base === widgetName ) {
					// Skip if blockFilter is provided and the block is not found in the widget content.
					if (
						widgetName === 'block' &&
						! widgets.data[ c ].rendered.includes( blockFilter )
					) {
						continue;
					}
					const deleteWidgetPayload = {
						force: true,
					};

					await client.delete(
						`${ widgetEndpoint }/${ widgets.data[ c ].id }`,
						deleteWidgetPayload
					);
				}
			}
		}
	}

	async createCustomer(
		customerData: CustomerType,
		billingAddress: AddressType,
		shippingAddress: AddressType
	): Promise< number > {
		const client = this.getAdminClient();
		const customerCreationData = {
			...customerData,
			username: customerData.username,
			firstname: billingAddress.firstname,
			lastname: billingAddress.lastname,
			password_confirm: customerData.password,
			roles: [ 'customer' ],
			billing: {
				...billingAddress,
				first_name: billingAddress.firstname,
				last_name: billingAddress.lastname,
				address_1: billingAddress.addressfirstline,
				address_2: billingAddress.addresssecondline,
				country: billingAddress.country_code,
			},
			shipping: {
				...shippingAddress,
				first_name: shippingAddress.firstname,
				last_name: shippingAddress.lastname,
				address_1: shippingAddress.addressfirstline,
				address_2: shippingAddress.addresssecondline,
				country: billingAddress.country_code,
			},
		};
		const customer = await client.post(
			userEndpoint,
			customerCreationData
		);
		return customer.data.id;
	}

	async recreateCustomer(
		customerData: CustomerType,
		billing: AddressType,
		shipping: AddressType
	): Promise< number > {
		await this.deleteCustomerByEmailAddress( customerData.email );
		await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
		return this.createCustomer( customerData, billing, shipping );
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
