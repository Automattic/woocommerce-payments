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
 * BalanceBlockProps
 *
 * @typedef {Object} BalanceBlockProps
 *
 * @property {string} id											The balance block id. Used to link the title and amount.
 * @property {string} title											The balance title.
 * @property {string} currencyCode									Currency code of the balance block.
 * @property {React.ReactElement< typeof BalanceTooltip >} tooltip	The tooltip element.
 * @property {number} [amount]										Optional. The balance amount.
 * @property {boolean} [isLoading]									Optional. Whether the balance block is loading.
 */
interface BalanceBlockProps {
	id: string;
	title: string;
	currencyCode: string;
	tooltip?: React.ReactElement< typeof BalanceTooltip >;
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
	id,
	title,
	currencyCode,
	tooltip,
	amount = 0,
	isLoading = false,
} ) => {
	return (
		<div className="wcpay-account-balances__balances__item">
			<p
				id={ id }
				className="wcpay-account-balances__balances__item__title"
			>
				<span>{ title }</span>
				{ ! isLoading && tooltip }
			</p>
			<p
				className="wcpay-account-balances__balances__item__amount"
				aria-labelledby={ id }
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
