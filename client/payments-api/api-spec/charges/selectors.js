/** @format */

/**
 * Internal dependencies
 */
import { DEFAULT_REQUIREMENT } from '../../constants';

const getCharge = ( getResource, requireResource ) => (
	chargeId,
	requirement = DEFAULT_REQUIREMENT
) => {
	return requireResource( requirement, chargeId ).data || {};
};

const isChargeWaitingForInitialLoad = ( getResource ) => ( transactionId ) => {
	return ! getResource( transactionId ).lastReceived;
};

const isChargeLoading = ( getResource ) => ( transactionId ) => {
	const transaction = getResource( transactionId );
	return ( ! transaction.lastReceived ) || ( transaction.lastRequested > transaction.lastReceived );
};

export default {
	getCharge,
	isChargeWaitingForInitialLoad,
	isChargeLoading,
};
