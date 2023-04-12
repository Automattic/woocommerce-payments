/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { displayStatus } from 'deposits/strings';
import Pill from 'components/pill';

const mappings: {
	[ key: string ]: 'primary' | 'success' | 'alert' | 'danger' | 'light';
} = {
	estimated: 'light',
	pending: 'alert',
	in_transit: 'success',
	paid: 'success',
	failed: 'danger',
	canceled: 'danger',
};

/**
 * Renders a deposits status pill.
 * Based off of the Pill component found components/pill.
 *
 * @return {JSX.Element} Deposit status pill.
 */
const DepositStatusPill: React.FC< {
	status: string;
} > = ( { status } ): JSX.Element => {
	const label = displayStatus[ status as keyof typeof displayStatus ]
		? displayStatus[ status as keyof typeof displayStatus ]
		: __( 'Unknown', 'woocommerce-payments' );

	const type = status && mappings[ status ] ? mappings[ status ] : 'light';

	return <Pill type={ type }>{ label }</Pill>;
};

export default DepositStatusPill;
