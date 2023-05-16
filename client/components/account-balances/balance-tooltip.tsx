/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import wcpayTracks from 'tracks';

type BalanceTooltipProps = {
	label: string;
	content: React.ReactNode;
};

const BalanceTooltip: React.FC< BalanceTooltipProps > = ( {
	label,
	content,
} ) => {
	const onClick = () => {
		wcpayTracks.recordEvent(
			wcpayTracks.events.OVERVIEW_BALANCE_TOOLTIP_CLICK,
			{
				context: label,
			}
		);
	};

	return (
		<ClickTooltip
			content={ content }
			className="wcpay-account-balances__balances__item__tooltip"
			onClick={ onClick }
		>
			<div
				className="wcpay-account-balances__balances__item__tooltip-button"
				role="button"
				aria-label={ label }
				tabIndex={ 0 }
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
