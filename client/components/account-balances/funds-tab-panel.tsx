/**
 * External dependencies
 */
import * as React from 'react';
import { formatCurrency } from 'wcpay/utils/currency';
import { useAllDepositsOverviews } from 'wcpay/data';
import { Flex, TabPanel } from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import LoadingFundsTabTable from './loading-funds-tab-panel';
import { currencyBalanceString, fundLabelStrings } from './strings';

const AccountBalancesTabTable: React.FC = () => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies } = overviews;

	if ( isLoading || currencies.length === 0 ) {
		return <LoadingFundsTabTable />;
	}

	const funds: TabPanel.Tab[] = currencies.map(
		( overview: AccountOverview.Overview ) => ( {
			name: overview.currency,
			title: sprintf(
				currencyBalanceString,
				overview.currency.toUpperCase()
			),
			available_funds: overview.available.amount,
			pending_funds: overview.pending.amount,
			reserve_funds: overview.pending.amount,
		} )
	);

	return (
		<TabPanel tabs={ funds }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.available }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ formatCurrency( tab.available_funds ) }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.pending }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ formatCurrency( tab.pending_funds ) }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.reserve }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ formatCurrency( tab.reserve_funds ) }
						</p>
					</div>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabTable;
