/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Tooltip from 'components/tooltip';
import {
	documentationUrls,
	fundTooltipStrings,
	learnMoreString,
} from './strings';
import { balanceType } from './balance-block';

type BalanceTooltipProps = {
	type: balanceType;
	delayDays?: number;
	isNegativeBalance?: boolean;
	className?: string;
};

const BalanceTooltip: React.FC< BalanceTooltipProps > = ( {
	type,
	delayDays,
	isNegativeBalance,
	className,
} ) => {
	let content = null;

	if ( type === 'available' ) {
		content = (
			<>
				{ fundTooltipStrings.available }{ ' ' }
				<a
					target="_blank"
					rel="noopener noreferrer"
					href={ documentationUrls.depositSchedule }
				>
					{ learnMoreString }
				</a>
			</>
		);
	}

	if ( type === 'available' && isNegativeBalance ) {
		content = (
			<a
				target="_blank"
				rel="noopener noreferrer"
				href={ documentationUrls.negativeBalance }
			>
				{ fundTooltipStrings.availableNegativeBalance }
			</a>
		);
	}

	if ( type === 'pending' ) {
		content = (
			<>
				{ sprintf( fundTooltipStrings.pending, delayDays ) }{ ' ' }
				<a
					target="_blank"
					rel="noopener noreferrer"
					href={ documentationUrls.depositSchedule }
				>
					{ learnMoreString }
				</a>
			</>
		);
	}

	if ( type === 'reserved' ) {
		content = (
			<>
				{ fundTooltipStrings.reserved }{ ' ' }
				<a
					target="_blank"
					rel="noopener noreferrer"
					href={ documentationUrls.reservedFunds }
				>
					{ learnMoreString }
				</a>
			</>
		);
	}

	return (
		<Tooltip content={ content } ignoreMouseHover>
			<div className={ className }>
				<HelpOutlineIcon size={ 18 } />
			</div>
		</Tooltip>
	);
};

export default BalanceTooltip;
