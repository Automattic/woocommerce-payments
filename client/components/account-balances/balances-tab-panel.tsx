/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, TabPanel } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
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
		isLoading: isAccountOverviewsLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;
	const {
		selectedCurrency,
		setSelectedCurrency,
		isLoading: isSelectedCurrencyLoading,
	} = useSelectedCurrency();

	const onTabSelect = ( tabName: BalanceTab[ 'name' ] ) => {
		setSelectedCurrency( tabName );
	};

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

	if ( ! isAccountOverviewsLoading && currencies.length !== 0 ) {
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

	const isLoading = isSelectedCurrencyLoading || isAccountOverviewsLoading;

	// Selected currency is not valid if it is not in the list of deposit currencies.
	const isSelectedCurrencyValid =
		selectedCurrency &&
		depositCurrencyTabs.some( ( tab ) => tab.name === selectedCurrency );

	return (
		<TabPanel
			tabs={ depositCurrencyTabs }
			onSelect={ onTabSelect }
			initialTabName={
				isSelectedCurrencyValid ? selectedCurrency : undefined
			}
		>
			{ ( tab: BalanceTab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<BalanceBlock
						type="available"
						amount={ tab.availableFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
					/>
					<BalanceBlock
						type="pending"
						amount={ tab.pendingFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
					/>
					<BalanceBlock
						type="reserved"
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
