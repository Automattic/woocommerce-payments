/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, TabPanel } from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import { getCurrencyTabTitle } from './utils';
import BalanceBlock from './balance-block';
import BalanceTooltip from './balance-tooltip';
import {
	documentationUrls,
	fundLabelStrings,
	fundTooltipStrings,
	learnMoreString,
} from './strings';

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
 * @param {number} delayDays	  The account's pending period in days.
 */
type BalanceTab = {
	name: string;
	title: string;
	currencyCode: string;
	availableFunds: number;
	pendingFunds: number;
	delayDays: number;
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
			delayDays: 0,
		},
	];

	const { currencies, account } = overviews;

	if ( ! isLoading && currencies.length !== 0 ) {
		depositCurrencyTabs = currencies.map(
			( overview: AccountOverview.Overview ) => ( {
				name: overview.currency,
				title: getCurrencyTabTitle( overview.currency ),
				currencyCode: overview.currency,
				availableFunds: overview.available?.amount ?? 0,
				pendingFunds: overview.pending?.amount ?? 0,
				delayDays: account.deposits_schedule.delay_days,
			} )
		);
	}

	return (
		<TabPanel tabs={ depositCurrencyTabs }>
			{ ( tab: BalanceTab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<BalanceBlock
						id={ `wcpay-account-balances-${ tab.currencyCode }-available` }
						title={ fundLabelStrings.available }
						amount={ tab.availableFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
						tooltip={
							<BalanceTooltip
								label={ `${ fundLabelStrings.available } tooltip` }
								content={
									tab.availableFunds < 0 ? (
										<a
											target="_blank"
											rel="noopener noreferrer"
											href={
												documentationUrls.negativeBalance
											}
										>
											{
												fundTooltipStrings.availableNegativeBalance
											}
										</a>
									) : (
										<>
											{ fundTooltipStrings.available }{ ' ' }
											<a
												target="_blank"
												rel="noopener noreferrer"
												href={
													documentationUrls.depositSchedule
												}
											>
												{ learnMoreString }
											</a>
										</>
									)
								}
							/>
						}
					/>
					<BalanceBlock
						id={ `wcpay-account-balances-${ tab.currencyCode }-pending` }
						title={ fundLabelStrings.pending }
						amount={ tab.pendingFunds }
						currencyCode={ tab.currencyCode }
						isLoading={ isLoading }
						tooltip={
							<BalanceTooltip
								label={ `${ fundLabelStrings.pending } tooltip` }
								content={
									<>
										{ sprintf(
											fundTooltipStrings.pending,
											tab.delayDays
										) }{ ' ' }
										<a
											target="_blank"
											rel="noopener noreferrer"
											href={
												documentationUrls.depositSchedule
											}
										>
											{ learnMoreString }
										</a>
									</>
								}
							/>
						}
					/>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
