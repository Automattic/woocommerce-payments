/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { displayStatus } from 'deposits/strings';
import Chip, { ChipType } from 'components/chip';
import type { DepositStatus } from 'wcpay/types/deposits';

/**
 * Maps a DepositStatus to a ChipType.
 */
const mappings: Record< DepositStatus, ChipType > = {
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
	status: DepositStatus;
} > = ( { status } ): JSX.Element => (
	<Chip type={ mappings[ status ] } message={ displayStatus[ status ] } />
);

export default DepositStatusChip;
