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
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import { getCurrencyTabTitle } from './utils';
import BalanceBlock from './balance-block';
import BalanceTooltip from './balance-tooltip';
import { documentationUrls, fundLabelStrings } from './strings';
import InstantDepositButton from 'deposits/instant-deposits';

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
	instantBalance?: AccountOverview.InstantBalance;
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

	if ( isLoading ) {
		// While the data is loading, we show a loading currency tab.
		const loadingTabs: BalanceTab[] = [
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
				{ ( tab: BalanceTab ) => (
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__balances"
					>
						<BalanceBlock
							id={ `wcpay-account-balances-${ tab.currencyCode }-available` }
							title={ fundLabelStrings.available }
							amount={ tab.availableFunds }
							currencyCode={ tab.currencyCode }
							isLoading
						/>
						<BalanceBlock
							id={ `wcpay-account-balances-${ tab.currencyCode }-pending` }
							title={ fundLabelStrings.pending }
							amount={ tab.pendingFunds }
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
			delayDays: account.deposits_schedule.delay_days,
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
			{ ( tab: BalanceTab ) => (
				<>
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__balances"
					>
						<BalanceBlock
							id={ `wcpay-account-balances-${ tab.currencyCode }-available` }
							title={ fundLabelStrings.available }
							amount={ tab.availableFunds }
							currencyCode={ tab.currencyCode }
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
							tooltip={
								<BalanceTooltip
									label={ `${ fundLabelStrings.pending } tooltip` }
									content={
										tab.pendingFunds < 0
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
											  } )
									}
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
			) }
		</TabPanel>
	);
};

export default AccountBalancesTabPanel;
