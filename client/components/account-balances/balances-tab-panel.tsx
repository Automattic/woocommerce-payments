/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, TabPanel } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import { fundLabelStrings } from './strings';
import { getCurrencyTabTitle } from './utils';
import BalanceBlock from './balance-block';

/**
 * Renders an account balances panel with tab navigation for each deposit currency.
 *
 * @return {JSX.Element} Rendered balances panel with tab navigation for each currency.
 */
const AccountBalancesTabPanel: React.FC = () => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	// While the data is loading, we show the default currency tab.
	let depositCurrencyTabs: TabPanel.Tab[] = [
		{
			name: wcpaySettings.accountDefaultCurrency,
			title: getCurrencyTabTitle( wcpaySettings.accountDefaultCurrency ),
		},
	];

	const { currencies } = overviews;

	if ( ! isLoading && currencies.length !== 0 ) {
		depositCurrencyTabs = currencies.map(
			( overview: AccountOverview.Overview ) => ( {
				name: overview.currency,
				title: getCurrencyTabTitle( overview.currency ),
				currencyCode: overview.currency,
				availableFunds: overview.available.amount,
				pendingFunds: overview.pending.amount,
				reserveFunds: 0, // TODO: Add reserve funds to the overview object.
			} )
		);
	}

	return (
		<TabPanel tabs={ depositCurrencyTabs }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<BalanceBlock
						title={ fundLabelStrings.available }
						amount={ tab.availableFunds }
						currencyCode={ tab.currency_code }
						isLoading={ isLoading }
					/>
					<BalanceBlock
						title={ fundLabelStrings.pending }
						amount={ tab.pendingFunds }
						currencyCode={ tab.currency_code }
						isLoading={ isLoading }
					/>
					<BalanceBlock
						title={ fundLabelStrings.reserve }
						amount={ tab.reserveFunds }
						currencyCode={ tab.currency_code }
						isLoading={ isLoading }
					/>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
