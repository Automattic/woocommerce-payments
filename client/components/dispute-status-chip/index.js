/** @format **/

/**
 * Internal dependencies
 */
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from 'utils';
import { isDueWithin } from 'wcpay/disputes/utils';
import { disputeAwaitingResponseStatuses } from 'wcpay/disputes/filters/config';

const DisputeStatusChip = ( { status, dueBy } ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );

	const needsResponse = disputeAwaitingResponseStatuses.includes( status );
	const isUrgent = needsResponse && isDueWithin( { dueBy, days: 3 } );

	let type = mapping.type || 'light';
	if ( isUrgent ) {
		type = 'alert';
	}

	return <Chip message={ message } type={ type } />;
};

export default DisputeStatusChip;
