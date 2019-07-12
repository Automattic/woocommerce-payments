/** @format */

function createPaymentsApiSpec() {
	return {
		name: 'wcPaymentsApi',
		mutations: {},
		selectors: {},
		operations: {
			read( resourceNames ) {
				if ( document.hidden ) {
					// Don't do any read updates while the tab isn't active.
					return [];
				}

				return [];
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
