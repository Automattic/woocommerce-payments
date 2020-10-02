/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import displayStatus from './mappings';
import Chip from '../chip';
import { formatStringValue } from 'utils';

const PaymentStatusChip = ( { status } ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type = mapping.type || 'light';
	return <Chip message={ message } type={ type } />;
};

export default PaymentStatusChip;
