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
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ label }
		/>
	);
};

export default BalanceTooltip;
