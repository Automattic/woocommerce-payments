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
				if ( document.hidden ) {
					// Don't do any read updates while the tab isn't active.
					return [];
				}

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
