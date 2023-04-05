/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, TabPanel } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import { getCurrencyTabTitle } from './utils';
import BalanceBlock from './balance-block';
import BalanceTooltip from './balance-tooltip';
import { documentationUrls, fundLabelStrings } from './strings';

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
									tab.availableFunds < 0
										? interpolateComponents( {
												mixedString: __(
													'{{learnMoreLink}}Learn more{{/learnMoreLink}} about why your account balance may be negative.',
													'woocommerce-payments'
												),
												components: {
													learnMoreLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<a
															rel="external noopener noreferrer"
															target="_blank"
															href={
																documentationUrls.negativeBalance
															}
														/>
													),
												},
										  } )
										: interpolateComponents( {
												mixedString: __(
													'The amount of funds available to be deposited. {{learnMoreLink}}Learn more.{{/learnMoreLink}}',
													'woocommerce-payments'
												),
												components: {
													learnMoreLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<a
															rel="external noopener noreferrer"
															target="_blank"
															href={
																documentationUrls.depositSchedule
															}
														/>
													),
												},
										  } )
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
								content={ interpolateComponents( {
									mixedString: sprintf(
										_n(
											'The amount of funds still in the %d day pending period. {{learnMoreLink}}Learn more.{{/learnMoreLink}}',
											'The amount of funds still in the %d day pending period. {{learnMoreLink}}Learn more.{{/learnMoreLink}}',
											tab.delayDays,
											'woocommerce-payments'
										),
										tab.delayDays
									),
									components: {
										learnMoreLink: (
											// eslint-disable-next-line jsx-a11y/anchor-has-content
											<a
												rel="external noopener noreferrer"
												target="_blank"
												href={
													documentationUrls.depositSchedule
												}
											/>
										),
									},
								} ) }
							/>
						}
					/>
				</Flex>
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
