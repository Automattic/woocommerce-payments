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
 * BalanceTab
 *
 * @typedef {Object} BalanceTab
 *
 * @param {string} name           Name of the tab.
 * @param {string} title          Title of the tab.
 * @param {string} currencyCode   Currency code of the tab.
 * @param {number} availableFunds Available funds of the tab.
 * @param {number} pendingFunds   Pending funds of the tab.
 * @param {number} reservedFunds  Reserved funds of the tab.
 */
type BalanceTab = {
	name: string;
	title: string;
	currencyCode: string;
	availableFunds: number;
	pendingFunds: number;
	reservedFunds: number;
};

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
	let depositCurrencyTabs: BalanceTab[] = [
		{
			name: wcpaySettings.accountDefaultCurrency,
			title: getCurrencyTabTitle( wcpaySettings.accountDefaultCurrency ),
			currencyCode: wcpaySettings.accountDefaultCurrency,
			availableFunds: 0,
			pendingFunds: 0,
			reservedFunds: 0,
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
				reservedFunds: 0, // TODO: Add reserve funds to the overview object.
			} )
		);
	}

	return (
		<TabPanel tabs={ depositCurrencyTabs }>
			{ ( tab: BalanceTab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<BalanceBlock
						title={ fundLabelStrings.available }
						amount={ tab.availableFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
					/>
					<BalanceBlock
						title={ fundLabelStrings.pending }
						amount={ tab.pendingFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
					/>
					<BalanceBlock
						title={ fundLabelStrings.reserved }
						amount={ tab.reservedFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
					/>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
