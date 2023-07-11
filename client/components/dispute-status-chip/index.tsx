/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { DisputeStatus } from 'wcpay/types/disputes';
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from 'utils';

interface Props {
	status: DisputeStatus | string;
	isUrgent?: boolean;
}
const DisputeStatusChip: React.FC< Props > = ( { status, isUrgent } ) => {
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
