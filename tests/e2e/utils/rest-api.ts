/**
 * External dependencies
 */
import axios, { AxiosInstance } from 'axios';

/**
 * Internal dependencies
 */
import { config } from '../config/default';

const userEndpoint = '/wc/v3/customers';
const ordersEndpoint = '/wc/v3/orders';
const widgetEndpoint = '/wp/v2/widgets';
const productsEndpoint = '/wc/v3/products';
const shippingZonesEndpoint = '/wc/v3/shipping/zones';

export type CustomerType = typeof config.users.customer;
export type AddressType = Omit<
	typeof config.addresses.customer.billing,
	'state'
> & { state?: string };

// Handles needed to tear down the zones/methods created for the ECE specs. The
// rest-of-world zone (built-in zone 0) can't be deleted, so we track the method
// instance ids we added to it and remove just those.
export type EceShippingZoneHandles = {
	usZoneId: number;
	rowMethodInstanceIds: number[];
};

class RestAPI {
	private baseUrl: string;

	constructor( baseUrl: string ) {
		if ( ! baseUrl ) {
			throw new Error( 'Base URL is required.' );
		}
		this.baseUrl = baseUrl;
	}

	private getAdminClient(): AxiosInstance {
		const base = this.baseUrl.replace( /\/$/, '' );
		return axios.create( {
			baseURL: `${ base }/wp-json`,
			auth: {
				username: config.users.admin.username,
				password: config.users.admin.password,
			},
		} );
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

		const customers = await client.get( userEndpoint, {
			params: {
				search: emailAddress,
				context: 'edit',
				role: 'all',
			},
		} );
		if ( customers.data && customers.data.length ) {
			for ( let c = 0; c < customers.data.length; c++ ) {
				await client.delete(
					`${ userEndpoint }/${ customers.data[ c ].id }`,
					{
						params: {
							force: true,
							reassign: 0,
						},
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
		const client = this.getAdminClient();

		const widgets = await client.get( widgetEndpoint, {
			params: {
				sidebar: widgetArea,
				context: 'edit',
			},
		} );

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

					await client.delete(
						`${ widgetEndpoint }/${ widgets.data[ c ].id }`,
						{
							params: {
								force: true,
							},
						}
					);
				}
			}
		}
	}

	/**
	 * Checks whether a widget is currently assigned to a sidebar.
	 * Matching mirrors deleteWidgets so callers can confirm a save persisted.
	 */
	async hasWidget(
		widgetArea: string,
		widgetName: string,
		blockFilter?: string
	): Promise< boolean > {
		const client = this.getAdminClient();

		const widgets = await client.get( widgetEndpoint, {
			params: {
				sidebar: widgetArea,
				context: 'edit',
			},
		} );

		if ( widgets.data && widgets.data.length ) {
			for ( let c = 0; c < widgets.data.length; c++ ) {
				if ( widgets.data[ c ].id_base === widgetName ) {
					// For block widgets, also require the specific block in the content.
					if (
						widgetName === 'block' &&
						! widgets.data[ c ].rendered.includes( blockFilter )
					) {
						continue;
					}

					return true;
				}
			}
		}

		return false;
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

		const products = await client.get( productsEndpoint, {
			params: { search: config.products.simple.name },
		} );

		if ( ! products.data || ! products.data.length ) {
			throw new Error( 'No products found.' );
		}

		const [ product ] = products.data;

		const order = await client.post( ordersEndpoint, {
			line_items: [
				{
					product_id: product.id,
					quantity: 1,
				},
			],
		} );

		return `${ order.data.id }`;
	}

	/**
	 * Provisions the shipping the ECE fake-sheet specs need: a US zone (Free +
	 * $11 flat rate) and the rest-of-world zone 0 (Free + $22 flat rate). Kept
	 * out of the global E2E setup so the no-shipping checkout the wc-blocks and
	 * Alipay specs assume stays intact for everyone else.
	 */
	async createEceShippingZones(): Promise< EceShippingZoneHandles > {
		const client = this.getAdminClient();

		// A crashed prior run can leave a stale US zone behind; drop it first so
		// we don't stack duplicates.
		const existingZones = await client.get( shippingZonesEndpoint );
		if ( existingZones.data && existingZones.data.length ) {
			for ( const zone of existingZones.data ) {
				if ( zone.name === 'United States' ) {
					await client.delete(
						`${ shippingZonesEndpoint }/${ zone.id }`,
						{ params: { force: true } }
					);
				}
			}
		}

		const usZone = await client.post( shippingZonesEndpoint, {
			name: 'United States',
			order: 0,
		} );
		const usZoneId = usZone.data.id;

		// Locations is a PUT that takes a bare array as the whole body - wrapping
		// it in an object or POSTing makes the zone match nothing.
		await client.put(
			`${ shippingZonesEndpoint }/${ usZoneId }/locations`,
			[ { code: 'US', type: 'country' } ]
		);

		await client.post( `${ shippingZonesEndpoint }/${ usZoneId }/methods`, {
			method_id: 'free_shipping',
		} );
		await client.post( `${ shippingZonesEndpoint }/${ usZoneId }/methods`, {
			method_id: 'flat_rate',
			settings: { cost: '11' },
		} );

		// Zone 0 is WooCommerce's built-in "Locations not covered by your other
		// zones" - it can't be created or deleted, only have methods added.
		const rowFree = await client.post(
			`${ shippingZonesEndpoint }/0/methods`,
			{ method_id: 'free_shipping' }
		);
		const rowFlat = await client.post(
			`${ shippingZonesEndpoint }/0/methods`,
			{ method_id: 'flat_rate', settings: { cost: '22' } }
		);

		return {
			usZoneId,
			rowMethodInstanceIds: [ rowFree.data.id, rowFlat.data.id ],
		};
	}

	/**
	 * Reverses createEceShippingZones: removes the methods added to zone 0 and
	 * force-deletes the US zone. Best-effort - a partial create still tears down
	 * whatever did land.
	 */
	async deleteEceShippingZones( {
		usZoneId,
		rowMethodInstanceIds,
	}: EceShippingZoneHandles ): Promise< void > {
		const client = this.getAdminClient();

		for ( const instanceId of rowMethodInstanceIds ) {
			await client
				.delete(
					`${ shippingZonesEndpoint }/0/methods/${ instanceId }`
				)
				.catch( () => undefined );
		}

		await client
			.delete( `${ shippingZonesEndpoint }/${ usZoneId }`, {
				params: { force: true },
			} )
			.catch( () => undefined );
	}
}

export default RestAPI;
