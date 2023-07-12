/** @format **/

/**
 * Internal dependencies
 */
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from 'utils';
import { isDisputeAwaitingResponse, isDueWithin } from 'wcpay/disputes/utils';

const DisputeStatusChip = ( { status, dueBy } ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );

	const needsResponse = isDisputeAwaitingResponse( { status } );
	const isUrgent = needsResponse && isDueWithin( { dueBy, days: 3 } );

	let type = mapping.type || 'light';
	if ( isUrgent ) {
		type = 'alert';
	}

	return <Chip message={ message } type={ type } isCompact />;
};

export default DisputeStatusChip;
