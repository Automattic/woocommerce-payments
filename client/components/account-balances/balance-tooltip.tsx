/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';

type BalanceTooltipProps = {
	label: string;
	content: React.ReactNode;
};

const BalanceTooltip: React.FC< BalanceTooltipProps > = ( {
	label,
	content,
} ) => {
	return (
		<ClickTooltip
			content={ content }
			className="wcpay-account-balances__balances__item__tooltip"
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
