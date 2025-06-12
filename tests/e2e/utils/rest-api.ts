/**
 * External dependencies
 */
import { request, APIRequestContext } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../config/default';

const userEndpoint = '/wc/v3/customers';
const ordersEndpoint = '/wc/v3/orders';
const widgetEndpoint = '/wp/v2/widgets';
const productsEndpoint = '/wc/v3/products';

export type CustomerType = typeof config.users.customer;
export type AddressType = Omit<
	typeof config.addresses.customer.billing,
	'state'
> & { state?: string };

class RestAPI {
	private baseUrl: string;
	private apiContext!: APIRequestContext;

	constructor( baseUrl: string ) {
		if ( ! baseUrl ) {
			throw new Error( 'Base URL is required.' );
		}
		this.baseUrl = baseUrl;
	}

	private async getAdminClient() {
		if ( ! this.apiContext ) {
			if ( ! this.baseUrl.endsWith( '/' ) ) {
				this.baseUrl += '/';
			}

			this.baseUrl += '?rest_route=';

			// Create a new API context with authentication
			this.apiContext = await request.newContext( {
				baseURL: this.baseUrl,
				extraHTTPHeaders: {
					Authorization:
						'Basic ' +
						Buffer.from(
							`${ config.users.admin.username }:${ config.users.admin.password }`
						).toString( 'base64' ),
					'Content-Type': 'application/json',
				},
			} );
		}
		return this.apiContext;
	}

	/**
	 * Deletes a customer account by their email address if the user exists.
	 *
	 * @param {string} emailAddress Customer user account email address.
	 * @return {Promise<void>}
	 */
	async deleteCustomerByEmailAddress(
		emailAddress: string
	): Promise< void > {
		const client = await this.getAdminClient();

		const query = {
			search: emailAddress,
			context: 'edit',
			role: 'all',
		};
		const response = await client.get( userEndpoint, { params: query } );
		const customers = await response.json();

		if ( customers && customers.length ) {
			for ( let c = 0; c < customers.length; c++ ) {
				const deleteUserPayload = {
					force: true,
					reassign: 0,
				};

				await client.delete(
					`${ userEndpoint }/${ customers[ c ].id }`,
					{
						data: deleteUserPayload,
					}
				);
			}
		}
	}

	async deleteWidgets(
		widgetArea: string,
		widgetName: string,
		blockFilter?: string
	): Promise< void > {
		const client = await this.getAdminClient();

		const query = {
			sidebar: widgetArea,
			context: 'edit',
		};
		const response = await client.get( widgetEndpoint, { params: query } );
		const widgets = await response.json();

		if ( widgets && widgets.length ) {
			for ( let c = 0; c < widgets.length; c++ ) {
				if ( widgets[ c ].id_base === widgetName ) {
					// Skip if blockFilter is provided and the block is not found in the widget content.
					if (
						widgetName === 'block' &&
						! widgets[ c ].rendered.includes( blockFilter )
					) {
						continue;
					}
					const deleteWidgetPayload = {
						force: true,
					};

					await client.delete(
						`${ widgetEndpoint }/${ widgets[ c ].id }`,
						{ data: deleteWidgetPayload }
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
		const client = await this.getAdminClient();
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
		const response = await client.post( userEndpoint, {
			data: customerCreationData,
		} );
		const customer = await response.json();
		return customer.id;
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
		const client = await this.getAdminClient();

		const response = await client.get( `${ productsEndpoint }`, {
			params: { search: config.products.simple.name },
		} );
		const products = await response.json();

		if ( ! products || ! products.length ) {
			throw new Error( 'No products found.' );
		}

		const [ product ] = products;

		const orderResponse = await client.post( ordersEndpoint, {
			data: {
				line_items: [
					{
						product_id: product.id,
						quantity: 1,
					},
				],
			},
		} );
		const order = await orderResponse.json();

		return `${ order.id }`;
	}

	async dispose() {
		if ( this.apiContext ) {
			await this.apiContext.dispose();
		}
	}
}

export default RestAPI;
