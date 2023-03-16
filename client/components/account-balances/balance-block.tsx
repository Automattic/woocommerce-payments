/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from 'components/loadable';

/**
 * BalanceBlockProps
 *
 * @typedef {Object} BalanceBlockProps
 *
 * @property {string} title        Title of the balance block. eg "Available Funds".
 * @property {string} currencyCode Currency code of the balance block.
 * @property {number} [amount]     Optional. The balance amount.
 * @property {boolean} [isLoading] Optional. Whether the balance block is loading.
 */
interface BalanceBlockProps {
	title: string;
	currencyCode: string;
	amount?: number;
	isLoading?: boolean;
}

/**
 * Renders a balance block including a title and amount.
 *
 * @param {BalanceBlockProps} props   Balance block props. See `BalanceBlockProps` interface.
 * @param {string} props.title        Title of the balance block. eg "Available Funds".
 * @param {string} props.currencyCode Currency code of the balance block.
 * @param {number} [props.amount]     Optional. The balance amount.
 * @param {boolean} [props.isLoading] Optional. Whether the balance block is loading.
 *
 * @return {JSX.Element} Rendered balance element.
 */
const BalanceBlock: React.FC< BalanceBlockProps > = ( {
	title,
	currencyCode,
	amount = 0,
	isLoading = false,
} ) => {
	return (
		<div className="wcpay-account-balances__balances__item">
			<p className="wcpay-account-balances__balances__item__title">
				{ title }
			</p>
			<p className="wcpay-account-balances__balances__item__amount">
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
