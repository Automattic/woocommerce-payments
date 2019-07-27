/** @format */

/**
 * Internal dependencies.
 */
import transactions from './transactions';

function createPaymentsApiSpec() {
	return {
		name: 'wcPaymentsApi',
		mutations: {},
		selectors: {
			...transactions.selectors,
		},
		operations: {
			read( resourceNames ) {
				return [
					...transactions.operations.read( resourceNames ),
				];
			},
			update( resourceNames, data ) {
				return [];
			},
			updateLocally( resourceNames, data ) {
				return [];
			},
		},
	};
}

export default createPaymentsApiSpec();
