/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import displayStatus from './mappings';
import Chip from '../chip';

interface Props {
	status: keyof typeof displayStatus;
}

const PaymentStatusChip: React.FC< Props > = ( { status } ) => {
	const mapping = displayStatus[ status ];
	const message = mapping.message;
	const type = mapping.type;
	return <Chip message={ message } type={ type } />;
};

export default PaymentStatusChip;
