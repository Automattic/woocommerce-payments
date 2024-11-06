/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import Chip, { ChipType } from 'components/chip';
import type { CachedDeposit, DepositStatus } from 'wcpay/types/deposits';
import { depositStatusLabels } from 'wcpay/deposits/strings';

/**
 * Maps a DepositStatus to a ChipType.
 */
const mappings: Record< DepositStatus, ChipType > = {
	pending: 'warning',
	in_transit: 'primary',
	paid: 'success',
	failed: 'alert',
	canceled: 'light',
};

/**
 * Renders a deposits status chip.
 */
const DepositStatusChip: React.FC< {
	deposit: Pick< CachedDeposit, 'status' | 'type' >;
} > = ( { deposit } ) => {
	let message = depositStatusLabels[ deposit.status ];

	// Withdrawals are displayed as 'Deducted' instead of 'Paid'.
	if ( deposit.type === 'withdrawal' && deposit.status === 'paid' ) {
		message = depositStatusLabels.deducted;
	}
	return <Chip type={ mappings[ deposit.status ] } message={ message } />;
};

export default DepositStatusChip;
