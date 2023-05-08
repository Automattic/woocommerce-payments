/** @format **/
import React from 'react';

/**
 * Internal dependencies
 */
import Chip from '../chip';
import displayStatus from './mappings';

interface Props {
	status: keyof typeof displayStatus;
}

const DisputeStatusChip: React.FC< Props > = ( { status } ) => {
	const mapping = displayStatus[ status ];
	const message = mapping.message;
	const type = mapping.type;

	return <Chip message={ message } type={ type } isCompact />;
};

export default DisputeStatusChip;
