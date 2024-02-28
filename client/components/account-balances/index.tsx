/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, TabPanel } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import { getCurrencyTabTitle } from './utils';
import BalanceBlock from './balance-block';
import {
	TotalBalanceTooltip,
	AvailableBalanceTooltip,
} from './balance-tooltip';
import { fundLabelStrings } from './strings';
import InstantDepositButton from 'deposits/instant-deposits';
import { recordEvent } from 'tracks';
import type * as AccountOverview from 'wcpay/types/account-overview';
import './style.scss';

/**
 * BalanceTabProps
 *
 * @typedef {Object} BalanceTab
 *
 * @param {string} name           Name of the tab.
 * @param {string} title          Title of the tab.
 * @param {string} currencyCode   Currency code of the tab.
 * @param {number} availableFunds Available funds of the tab.
 * @param {number} pendingFunds   Pending funds of the tab.
 * @param {number} delayDays	  The account's pending period in days.
 */
type BalanceTabProps = {
	name: string;
	title: string;
	currencyCode: string;
	availableFunds: number;
	pendingFunds: number;
	delayDays: number;
	instantBalance?: AccountOverview.InstantBalance;
};

/**
 * Renders an account balances panel with tab navigation for each deposit currency.
 *
 * @return {JSX.Element} Rendered balances panel with tab navigation for each currency.
 */
const AccountBalances: React.FC = () => {
	const { overviews, isLoading } = useAllDepositsOverviews();
	const { selectedCurrency, setSelectedCurrency } = useSelectedCurrency();

	if ( ! isLoading && overviews.currencies.length === 0 ) {
		return null;
	}

	const onTabSelect = ( tabName: BalanceTabProps[ 'name' ] ) => {
		setSelectedCurrency( tabName );
		recordEvent( 'wcpay_overview_balances_currency_tab_click', {
			selected_currency: tabName,
		} );
	};

	if ( isLoading ) {
		// While the data is loading, we show a loading currency tab.
		const loadingTabs: BalanceTabProps[] = [
			{
				name: 'loading',
				title: getCurrencyTabTitle(
					wcpaySettings.accountDefaultCurrency
				),
				currencyCode: wcpaySettings.accountDefaultCurrency,
				availableFunds: 0,
				pendingFunds: 0,
				delayDays: 0,
			},
		];
		return (
			<TabPanel tabs={ loadingTabs }>
				{ ( tab: BalanceTabProps ) => (
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__balances"
					>
						<BalanceBlock
							id={ `wcpay-account-balances-${ tab.currencyCode }-total` }
							title={ fundLabelStrings.total }
							amount={ 0 }
							currencyCode={ tab.currencyCode }
							isLoading
						/>
						<BalanceBlock
							id={ `wcpay-account-balances-${ tab.currencyCode }-available` }
							title={ fundLabelStrings.available }
							amount={ 0 }
							currencyCode={ tab.currencyCode }
							isLoading
						/>
					</Flex>
				) }
			</TabPanel>
		);
	}

	const { currencies, account } = overviews;

	const depositCurrencyTabs = currencies.map(
		( overview: AccountOverview.Overview ) => ( {
			name: overview.currency,
			title: getCurrencyTabTitle( overview.currency ),
			currencyCode: overview.currency,
			availableFunds: overview.available?.amount ?? 0,
			pendingFunds: overview.pending?.amount ?? 0,
			delayDays: account?.deposits_schedule.delay_days ?? 0,
			instantBalance: overview.instant,
		} )
	);

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
			{ ( tab: BalanceTabProps ) => {
				const totalBalance = tab.availableFunds + tab.pendingFunds;

				return (
					<>
						<Flex
							gap={ 0 }
							className="wcpay-account-balances__balances"
						>
							<BalanceBlock
								id={ `wcpay-account-balances-${ tab.currencyCode }-total` }
								title={ fundLabelStrings.total }
								amount={ totalBalance }
								currencyCode={ tab.currencyCode }
								tooltip={
									<TotalBalanceTooltip
										balance={ totalBalance }
									/>
								}
							/>
							<BalanceBlock
								id={ `wcpay-account-balances-${ tab.currencyCode }-available` }
								title={ fundLabelStrings.available }
								amount={ tab.availableFunds }
								currencyCode={ tab.currencyCode }
								tooltip={
									<AvailableBalanceTooltip
										balance={ tab.availableFunds }
									/>
								}
							/>
						</Flex>
						{ tab.instantBalance && tab.instantBalance.amount > 0 && (
							<Flex
								gap={ 0 }
								className="wcpay-account-balances__instant-deposit"
							>
								<InstantDepositButton
									instantBalance={ tab.instantBalance }
								/>
							</Flex>
						) }
					</>
				);
			} }
		</TabPanel>
	);
};

export default AccountBalances;
