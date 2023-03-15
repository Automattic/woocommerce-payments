/**
 * External dependencies
 */
import * as React from 'react';
import { useAllDepositsOverviews } from 'wcpay/data';
import { Flex, TabPanel } from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import LoadingBalancesTabPanel from './loading-balances-tab-panel';
import { currencyBalanceString, fundLabelStrings } from './strings';
import BalanceBlock from './balance-block';

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

	// For a short period after isLoading resolves, the currencies will be empty,
	// so we need to render a loading state until the currencies are available.
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
			currencyCode: overview.currency,
			availableFunds: overview.available.amount,
			pendingFunds: overview.pending.amount,
			reserveFunds: 0, // TODO: Add reserve funds to the overview object.
		} )
	);

	return (
		<TabPanel tabs={ depositCurrencyTabs }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<BalanceBlock
						title={ fundLabelStrings.available }
						amount={ tab.availableFunds }
						currencyCode={ tab.currency_code }
					/>
					<BalanceBlock
						title={ fundLabelStrings.pending }
						amount={ tab.pendingFunds }
						currencyCode={ tab.currency_code }
					/>
					<BalanceBlock
						title={ fundLabelStrings.reserve }
						amount={ tab.reserveFunds }
						currencyCode={ tab.currency_code }
					/>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
