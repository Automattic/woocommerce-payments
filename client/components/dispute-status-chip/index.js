/** @format **/

/**
 * Internal dependencies
 */
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from '../../util';

const DisputeStatusChip = ( { status } ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type = mapping.type || 'light';

	return <Chip message={ message } type={ type } isCompact />;
};

export default DisputeStatusChip;
