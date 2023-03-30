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
 */
type BalanceTab = {
	name: string;
	title: string;
	currencyCode: string;
	availableFunds: number;
	pendingFunds: number;
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
	const { selectedCurrency, setSelectedCurrency } = useSelectedCurrency();

	if ( ! isLoading && overviews.currencies.length === 0 ) {
		return null;
	}

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
		},
	];

	const { currencies } = overviews;

	if ( ! isLoading && currencies.length !== 0 ) {
		depositCurrencyTabs = currencies.map(
			( overview: AccountOverview.Overview ) => ( {
				name: overview.currency,
				title: getCurrencyTabTitle( overview.currency ),
				currencyCode: overview.currency,
				availableFunds: overview.available?.amount ?? 0,
				pendingFunds: overview.pending?.amount ?? 0,
			} )
		);
	}

	// Selected currency is not valid if it is not in the list of deposit currencies.
	const isSelectedCurrencyValid =
		selectedCurrency &&
		depositCurrencyTabs.some( ( tab ) => tab.name === selectedCurrency );

	// Wrap TabPanel to allow a full re-render when the selected currency changes.
	// since TabPanel will not allow controlled tab selection, we use the initialTabName prop.
	const TabPanelComp: React.FC = () => {
		return (
			<TabPanel
				tabs={ depositCurrencyTabs }
				onSelect={ onTabSelect }
				initialTabName={
					isSelectedCurrencyValid ? selectedCurrency : undefined
				}
			>
				{ ( tab: BalanceTab ) => (
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__balances"
					>
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
					</Flex>
				) }
			</TabPanel>
		);
	};

	return <TabPanelComp />;
};

export default AccountBalancesTabPanel;
