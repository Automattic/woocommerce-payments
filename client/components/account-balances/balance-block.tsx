/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from 'components/loadable';
import BalanceTooltip from './balance-tooltip';

/**
 * balanceType
 */
export type balanceType = 'pending' | 'available' | 'reserved';

/**
 * BalanceBlockProps
 *
 * @typedef {Object} BalanceBlockProps
 *
 * @property {balanceType} type										Type of the balance block. eg "available".
 * @property {string} title											The balance title.
 * @property {string} currencyCode									Currency code of the balance block.
 * @property {React.ReactElement< typeof BalanceTooltip >} tooltip	The tooltip element.
 * @property {number} [amount]										Optional. The balance amount.
 * @property {boolean} [isLoading]									Optional. Whether the balance block is loading.
 */
interface BalanceBlockProps {
	type: balanceType;
	title: string;
	currencyCode: string;
	tooltip: React.ReactElement< typeof BalanceTooltip >;
	amount?: number;
	isLoading?: boolean;
}

/**
 * Renders a balance block including a title, amount and tooltip.
 *
 * @param {BalanceBlockProps} props   Balance block props. See `BalanceBlockProps` interface.
 *
 * @return {JSX.Element} Rendered balance element.
 */
const BalanceBlock: React.FC< BalanceBlockProps > = ( {
	type,
	title,
	currencyCode,
	tooltip,
	amount = 0,
	isLoading = false,
} ) => {
	const titleElementID = `wcpay-account-balances-${ currencyCode }-${ type }-title`;
	return (
		<div className="wcpay-account-balances__balances__item">
			<p
				id={ titleElementID }
				className="wcpay-account-balances__balances__item__title"
			>
				<span>{ title }</span>
				{ tooltip }
			</p>
			<p
				className="wcpay-account-balances__balances__item__amount"
				aria-labelledby={ titleElementID }
			>
				<Loadable
					isLoading={ isLoading }
					display="inline"
					placeholder="loading amount"
					value={ formatCurrency( amount, currencyCode ) }
				/>
			</p>
		</div>
	);
};

export default BalanceBlock;
