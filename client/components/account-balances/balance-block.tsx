/**
 * External dependencies
 */
import * as React from 'react';
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from 'components/loadable';

interface BalanceBlockProps {
	title: string;
	currencyCode: string;
	amount?: number;
	isLoading?: boolean;
}

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
