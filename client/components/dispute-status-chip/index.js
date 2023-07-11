/** @format **/

/**
 * Internal dependencies
 */
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from 'utils';

const DisputeStatusChip = ( { status, isUrgent } ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	let type = mapping.type || 'light';
	// If urgent, show as red.
	if ( isUrgent ) {
		type = 'alert';
	}
	return <Chip message={ message } type={ type } isCompact />;
};

export default DisputeStatusChip;
