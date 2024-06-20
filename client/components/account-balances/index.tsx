/**
 * External dependencies
 */
import React, { useState } from 'react';
import { useDispatch } from '@wordpress/data';
import { Flex } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import type * as AccountOverview from 'wcpay/types/account-overview';
import BalanceBlock from './balance-block';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import InlineNotice from '../inline-notice';
import InstantDepositButton from 'deposits/instant-deposits';
import SendMoneyIcon from 'assets/images/icons/send-money.svg?asset';
import {
	TotalBalanceTooltip,
	AvailableBalanceTooltip,
} from './balance-tooltip';
import { fundLabelStrings } from './strings';
import { ClickTooltip } from '../tooltip';
import { formatCurrency } from 'wcpay/utils/currency';
import { useAllDepositsOverviews } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import './style.scss';

const useInstantDepositNoticeState = () => {
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ isDismissed, setIsDismissed ] = useState(
		wcpaySettings.isInstantDepositNoticeDismissed
	);

	const setInstantDepositNoticeDismissed = () => {
		setIsDismissed( true );
		wcpaySettings.isInstantDepositNoticeDismissed = true;
		updateOptions( { wcpay_instant_deposit_notice_dismissed: true } );
	};

	return {
		isInstantDepositNoticeDismissed: isDismissed,
		handleDismissInstantDepositNotice: setInstantDepositNoticeDismissed,
	};
};

/**
 * Renders account balances for the selected currency.
 */
const AccountBalances: React.FC = () => {
	const { overviews, isLoading } = useAllDepositsOverviews();
	const { selectedCurrency } = useSelectedCurrency();

	const {
		isInstantDepositNoticeDismissed,
		handleDismissInstantDepositNotice,
	} = useInstantDepositNoticeState();

	if ( ! isLoading && overviews.currencies.length === 0 ) {
		return null;
	}

	if ( isLoading ) {
		// While the data is loading, we show a loading state for the balances.
		const loadingData = {
			name: 'loading',
			currencyCode: wcpaySettings.accountDefaultCurrency,
			availableFunds: 0,
			pendingFunds: 0,
			delayDays: 0,
		};

		return (
			<Flex gap={ 0 } className="wcpay-account-balances__balances">
				<BalanceBlock
					id={ `wcpay-account-balances-${ loadingData.currencyCode }-total` }
					title={ fundLabelStrings.total }
					amount={ 0 }
					currencyCode={ loadingData.currencyCode }
					isLoading
				/>
				<BalanceBlock
					id={ `wcpay-account-balances-${ loadingData.currencyCode }-available` }
					title={ fundLabelStrings.available }
					amount={ 0 }
					currencyCode={ loadingData.currencyCode }
					isLoading
				/>
			</Flex>
		);
	}

	const { currencies, account } = overviews;

	const depositCurrencyOverviews = currencies.map(
		( overview: AccountOverview.Overview ) => ( {
			name: overview.currency,
			currencyCode: overview.currency,
			availableFunds: overview.available?.amount ?? 0,
			pendingFunds: overview.pending?.amount ?? 0,
			delayDays: account?.deposits_schedule.delay_days ?? 0,
			instantBalance: overview.instant,
		} )
	);

	const selectedOverview =
		depositCurrencyOverviews.find(
			( overview ) => overview.name === selectedCurrency
		) || depositCurrencyOverviews[ 0 ];

	const totalBalance =
		selectedOverview.availableFunds + selectedOverview.pendingFunds;

	return (
		<>
			<Flex gap={ 0 } className="wcpay-account-balances__balances">
				<BalanceBlock
					id={ `wcpay-account-balances-${ selectedOverview.currencyCode }-total` }
					title={ fundLabelStrings.total }
					amount={ totalBalance }
					currencyCode={ selectedOverview.currencyCode }
					tooltip={ <TotalBalanceTooltip balance={ totalBalance } /> }
				/>
				<BalanceBlock
					id={ `wcpay-account-balances-${ selectedOverview.currencyCode }-available` }
					title={ fundLabelStrings.available }
					amount={ selectedOverview.availableFunds }
					currencyCode={ selectedOverview.currencyCode }
					tooltip={
						<AvailableBalanceTooltip
							balance={ selectedOverview.availableFunds }
						/>
					}
				/>
			</Flex>
			{ selectedOverview.instantBalance &&
				selectedOverview.instantBalance.amount > 0 && (
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__instant-deposit"
						direction="column"
						align="start"
					>
						{ ! isInstantDepositNoticeDismissed && (
							<InlineNotice
								className="wcpay-account-balances__instant-deposit-notice"
								icon={ <img src={ SendMoneyIcon } alt="" /> }
								isDismissible={ true }
								onRemove={ () =>
									handleDismissInstantDepositNotice()
								}
							>
								{ sprintf(
									__(
										/* translators: %$1$s: Available instant deposit amount, %2$s: Instant deposit fee percentage */
										/* 'Instantly deposit %1$s and get funds in your bank account in 30 mins for a %2$s%% fee.' */
										'Get %1$s via instant deposit. Funds are typically in your bank account within 30 mins. Fee: %2$s%%.',
										'woocommerce-payments'
									),
									formatCurrency(
										selectedOverview.instantBalance.amount,
										selectedOverview.instantBalance.currency
									),
									selectedOverview.instantBalance
										.fee_percentage
								) }
							</InlineNotice>
						) }

						<Flex justify="flex-start">
							<InstantDepositButton
								instantBalance={
									selectedOverview.instantBalance
								}
							/>
							{ isInstantDepositNoticeDismissed && ( // Show the tooltip only when the notice is dismissed.
								<ClickTooltip
									buttonIcon={ <HelpOutlineIcon /> }
									buttonLabel={ __(
										'Learn more about instant deposit',
										'woocommerce-payments'
									) }
									content={
										/* 'With instant deposit you can receive requested funds in your bank account within 30 mins for a 1.5% fee. Learn more' */

										interpolateComponents( {
											mixedString: sprintf(
												__(
													/* translators: %s: Instant deposit fee percentage */
													'With {{strong}}instant deposit{{/strong}} you can receive requested funds in your bank account within 30 mins for a %s%% fee. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
													'woocommerce-payments'
												),
												selectedOverview.instantBalance
													.fee_percentage
											),
											components: {
												strong: <strong />,
												learnMoreLink: (
													<Link
														href="https://woocommerce.com/document/woopayments/deposits/instant-deposits/"
														target="_blank"
														rel="noreferrer"
														type="external"
													/>
												),
											},
										} )
									}
								/>
							) }
						</Flex>
					</Flex>
				) }
		</>
	);
};

export default AccountBalances;
