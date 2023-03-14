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
import LoadingBalancesTabPanel from './loading-balances-tab-panel';
import { currencyBalanceString, fundLabelStrings } from './strings';

/**
 * Renders an account balances panel with tab navigation for each deposit currency.
 *
 * @return {React.FunctionComponent} Rendered balances panel with tab navigation for each currency.
 */
const AccountBalancesTabPanel: React.FC = () => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies } = overviews;

	if ( isLoading || currencies.length === 0 ) {
		return <LoadingBalancesTabPanel />;
	}

	const depositCurrencyTabs: TabPanel.Tab[] = currencies.map(
		( overview: AccountOverview.Overview ) => ( {
			name: overview.currency,
			title: sprintf(
				// string format: {currency} balance
				currencyBalanceString,
				overview.currency.toUpperCase()
			),
			currency_code: overview.currency,
			available_funds: overview.available.amount,
			pending_funds: overview.pending.amount,
			reserve_funds: 0, // TODO: Add reserve funds to the overview object.
		} )
	);

	return (
		<TabPanel tabs={ depositCurrencyTabs }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.available }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ formatCurrency(
								tab.available_funds,
								tab.currency_code
							) }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.pending }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ formatCurrency(
								tab.pending_funds,
								tab.currency_code
							) }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.reserve }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ formatCurrency(
								tab.reserve_funds,
								tab.currency_code
							) }
						</p>
					</div>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
