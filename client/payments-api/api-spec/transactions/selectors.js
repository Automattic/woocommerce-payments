
/**
 * Internal dependencies.
 */
import { DEFAULT_REQUIREMENT } from '../../constants';

const getTransactions = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	return [ {
		created: new Date(),
		type: '',
		source: {},
		order: { url: '#', number: 123 },
		customer: {},
		email: 'admin@test.com',
		country: 'USA',
		amount: 1234,
		fee: 1,
		net: 2,
		risk_level: '',
	} ];
}

export default {
	getTransactions,
};
