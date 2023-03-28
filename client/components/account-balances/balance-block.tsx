/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from 'components/loadable';
import { fundLabelStrings } from './strings';

/**
 * balanceType
 */
type balanceType = 'pending' | 'available';

/**
 * BalanceBlockProps
 *
 * @typedef {Object} BalanceBlockProps
 *
 * @property {balanceType} type    Type of the balance block. eg "available".
 * @property {string} currencyCode Currency code of the balance block.
 * @property {number} [amount]     Optional. The balance amount.
 * @property {boolean} [isLoading] Optional. Whether the balance block is loading.
 */
interface BalanceBlockProps {
	type: balanceType;
	currencyCode: string;
	amount?: number;
	isLoading?: boolean;
}

/**
 * Renders a balance block including a title and amount.
 *
 * @param {BalanceBlockProps} props   Balance block props. See `BalanceBlockProps` interface.
 * @param {balanceType} props.type    The balance type. eg "available".
 * @param {string} props.currencyCode Currency code of the balance block.
 * @param {number} [props.amount]     Optional. The balance amount.
 * @param {boolean} [props.isLoading] Optional. Whether the balance block is loading.
 *
 * @return {JSX.Element} Rendered balance element.
 */
const BalanceBlock: React.FC< BalanceBlockProps > = ( {
	type,
	currencyCode,
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
				{ fundLabelStrings[ type ] }
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
