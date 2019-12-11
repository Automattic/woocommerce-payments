/** @format */

/**
 * Internal dependencies
 */
import transactions from './transactions';
import charges from './charges';

function createPaymentsApiSpec() {
	return {
		name: 'wcPaymentsApi',
		mutations: {},
		selectors: {
			...transactions.selectors,
			...charges.selectors,
		},
		operations: {
			read( resourceNames ) {
				return [
					...transactions.operations.read( resourceNames ),
					...charges.operations.read( resourceNames ),
				];
			},
			/* eslint-disable */
			update( resourceNames, data ) {
				/* eslint-enable */
				return [];
			},
			/* eslint-disable */
			updateLocally( resourceNames, data ) {
				/* eslint-enable */
				return [];
			},
		},
	};
}

export default createPaymentsApiSpec();
