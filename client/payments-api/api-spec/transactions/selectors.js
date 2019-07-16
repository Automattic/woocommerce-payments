
/**
 * Internal dependencies.
 */
import { DEFAULT_REQUIREMENT } from '../../constants';

const getTransactions = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	const resourceName = 'transactions-list';
	return requireResource( requirement, resourceName ).data || {};
}

export default {
	getTransactions,
};
