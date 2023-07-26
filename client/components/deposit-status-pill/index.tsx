/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { displayStatus } from 'deposits/strings';
import Pill, { PillType } from 'components/pill';
import type { DepositStatus } from 'wcpay/types/deposits';

/**
 * Maps a DepositStatus to a PillType.
 */
const mappings: Record< DepositStatus, PillType > = {
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
	status: DepositStatus;
} > = ( { status } ): JSX.Element => {
	return <Pill type={ mappings[ status ] }>{ displayStatus[ status ] }</Pill>;
};

export default DepositStatusPill;
