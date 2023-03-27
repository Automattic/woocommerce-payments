/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import {
	documentationUrls,
	fundLabelStrings,
	fundTooltipStrings,
	learnMoreString,
} from './strings';
import { balanceType } from './balance-block';

type BalanceTooltipProps = {
	type: balanceType;
	delayDays?: number;
	isNegativeBalance?: boolean;
};

const BalanceTooltip: React.FC< BalanceTooltipProps > = ( {
	type,
	delayDays,
	isNegativeBalance,
} ) => {
	let content = null;
	const tooltipButtonLabel = `${ fundLabelStrings[ type ] } tooltip`;

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
		<ClickTooltip content={ content }>
			<div
				className="wcpay-account-balances__balances__item__tooltip-button"
				role="button"
				aria-label={ tooltipButtonLabel }
			>
				<HelpOutlineIcon
					size={ 18 }
					className="wcpay-account-balances__balances__item__tooltip-button__icon"
				/>
			</div>
		</ClickTooltip>
	);
};

export default BalanceTooltip;
