/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { displayStatus } from 'deposits/strings';
import Chip from 'components/chip';

const mappings: {
	[ key: string ]: 'primary' | 'success' | 'light' | 'warning' | 'alert';
} = {
	estimated: 'light',
	pending: 'warning',
	in_transit: 'success',
	paid: 'success',
	failed: 'alert',
	canceled: 'alert',
};

/**
 * Renders a deposits status chip.
 *
 * @return {JSX.Element} Deposit status chip.
 */
const DepositStatusChip: React.FC< {
	status: string;
} > = ( { status } ): JSX.Element => {
	const label = displayStatus[ status as keyof typeof displayStatus ]
		? displayStatus[ status as keyof typeof displayStatus ]
		: __( 'Unknown', 'woocommerce-payments' );

	const type = status && mappings[ status ] ? mappings[ status ] : 'light';

	return <Chip type={ type } message={ label } />;
};

export default DepositStatusChip;
