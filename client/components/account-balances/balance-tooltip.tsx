/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import { fundLabelStrings } from './strings';
import { balanceType } from './balance-block';

type BalanceTooltipProps = {
	type: balanceType;
	content: React.ReactNode;
};

const BalanceTooltip: React.FC< BalanceTooltipProps > = ( {
	type,
	content,
} ) => {
	const tooltipButtonLabel = `${ fundLabelStrings[ type ] } tooltip`;

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
