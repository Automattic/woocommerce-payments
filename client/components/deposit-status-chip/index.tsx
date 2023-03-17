/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import strings from './strings';

const mappings: {
	[ key: string ]: string;
} = {
	estimated: 'estimated',
	pending: 'processing',
	in_transit: 'sent',
	paid: 'sent',
	failed: 'failed',
};

type DepositStatusChip = {
	status?: string;
	isCompact?: boolean;
};

/**
 * Renders a deposits status chip.
 *
 * Based off of the Chip component found components/chip.
 *
 * @param {AccountOverview.Overview} props Deposits overview
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositStatusChip: React.FC< DepositStatusChip > = ( {
	status,
	isCompact,
}: DepositStatusChip ): JSX.Element => {
	// Validate the status given and map it to a type. Defaults to estimated.
	const type =
		status && mappings[ status ] ? mappings[ status ] : 'estimated';

	const classNames = [
		'deposit-status-chip',
		`chip-${ type }`,
		isCompact ? 'is-compact' : '',
	];

	return (
		<span className={ classNames.join( ' ' ).trim() }>
			{ strings[ type ] }
		</span>
	);
};

export default DepositStatusChip;
