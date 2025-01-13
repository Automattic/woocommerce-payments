/**
 * External dependencies
 */
import { HTTPClientFactory } from '@woocommerce/api';

/**
 * Internal dependencies
 */
import { config } from '../config/default';

const userEndpoint = '/wp/v2/users';

const getAdminClient = () =>
	HTTPClientFactory.build( process.env.BASE_URL )
		.withBasicAuth(
			config.users.admin.username,
			config.users.admin.password
		)
		.create();

/**
 * Deletes a customer account by their email address if the user exists.
 *
 * Copied from https://github.com/woocommerce/woocommerce/blob/trunk/packages/js/e2e-utils/src/flows/with-rest-api.js#L374
 *
 * @param {string} emailAddress Customer user account email address.
 * @return {Promise<void>}
 */
export const deleteCustomerByEmailAddress = async ( emailAddress: string ) => {
	const client = getAdminClient();

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
};
