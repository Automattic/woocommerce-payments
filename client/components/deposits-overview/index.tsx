/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { getHistory } from '@woocommerce/navigation';
import { Button, CardBody, CardFooter } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { getAdminUrl } from 'wcpay/utils';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { recordEvent } from 'tracks';
import OverviewCard from 'wcpay/components/overview-card';
import Loadable, { LoadableBlock } from 'wcpay/components/loadable';
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import RecentDepositsList from './recent-deposits-list';
import DepositSchedule from './deposit-schedule';
import {
	DepositMinimumBalanceNotice,
	NegativeBalanceDepositsPausedNotice,
	NewAccountWaitingPeriodNotice,
	NoFundsAvailableForDepositNotice,
	SuspendedDepositNotice,
	DepositFailureNotice,
} from './deposit-notices';
import { hasAutomaticScheduledDeposits } from 'wcpay/deposits/utils';
import useRecentDeposits from './hooks';
import './style.scss';

const DepositsLoadingState: React.FC = () => (
	<>
		<CardBody className="wcpay-deposits-overview__schedule__container">
			<Loadable
				isLoading
				aria-hidden
				placeholder={ __(
					'Available funds are automatically dispatched every day.',
					'woocommerce-payments'
				) }
			/>
		</CardBody>

		<CardBody className="wcpay-deposits-overview__heading">
			<span className="wcpay-deposits-overview__heading__title">
				{ __( 'Payout history', 'woocommerce-payments' ) }
			</span>
		</CardBody>

		<CardBody>
			<LoadableBlock isLoading numLines={ 4 } />
		</CardBody>

		<CardFooter className="wcpay-deposits-overview__footer">
			<Loadable
				isLoading
				aria-hidden
				placeholder={ __(
					'View full payout history',
					'woocommerce-payments'
				) }
			/>
			<Loadable
				isLoading
				aria-hidden
				placeholder={ __(
					'Change payout schedule',
					'woocommerce-payments'
				) }
			/>
		</CardFooter>
	</>
);

const DepositsOverview: React.FC = () => {
	const {
		account,
		overview,
		isLoading: isLoadingOverview,
	} = useSelectedCurrencyOverview();
	const isDepositsUnrestricted =
		wcpaySettings.accountStatus.deposits?.restrictions ===
		'deposits_unrestricted';
	const selectedCurrency =
		overview?.currency || wcpaySettings.accountDefaultCurrency;
	const { isLoading: isLoadingDeposits, deposits } =
		useRecentDeposits( selectedCurrency );

	const isLoading = isLoadingOverview || isLoadingDeposits;

	const availableFunds = overview?.available?.amount ?? 0;
	const pendingFunds = overview?.pending?.amount ?? 0;
	const totalFunds = availableFunds + pendingFunds;

	const minimumDepositAmount =
		wcpaySettings.accountStatus.deposits
			?.minimum_scheduled_deposit_amounts?.[ selectedCurrency ] ?? 0;
	const isAboveMinimumDepositAmount = availableFunds >= minimumDepositAmount;
	// If the total balance is negative, deposits may be paused.
	const isNegativeBalanceDepositsPaused = totalFunds < 0;
	// When there are funds pending but no available funds, deposits are paused.
	const isDepositAwaitingPendingFunds =
		availableFunds === 0 && pendingFunds > 0;
	const hasCompletedWaitingPeriod =
		wcpaySettings.accountStatus.deposits?.completed_waiting_period;
	const canChangeDepositSchedule =
		! account?.deposits_blocked && hasCompletedWaitingPeriod;
	// Only show the deposit history section if the page is finished loading and there are deposits. */ }
	const hasRecentDeposits = ! isLoading && deposits?.length > 0 && !! account;
	const hasScheduledDeposits = hasAutomaticScheduledDeposits(
		account?.deposits_schedule?.interval
	);
	const hasErroredExternalAccount =
		account?.default_external_accounts?.some(
			( externalAccount ) =>
				externalAccount.currency === selectedCurrency &&
				externalAccount.status === 'errored'
		) ?? false;

	const navigateToDepositsHistory = () => {
		recordEvent( 'wcpay_overview_deposits_view_history_click' );

		const history = getHistory();
		history.push(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/payouts',
			} )
		);
	};

	if (
		! isLoading &&
		! hasCompletedWaitingPeriod &&
		availableFunds === 0 &&
		pendingFunds === 0
	) {
		// If still in new account waiting period and account has no transactions,
		// don't render deposits card (nothing to show).
		return null;
	}

	return (
		<OverviewCard
			title={ __( 'Payouts', 'woocommerce-payments' ) }
			className="wcpay-deposits-overview"
			isLoading={ isLoading }
			LoadingState={ DepositsLoadingState }
		>
			{ /* Deposit schedule message */ }
			{ isDepositsUnrestricted && !! account && hasScheduledDeposits && (
				<CardBody className="wcpay-deposits-overview__schedule__container">
					<DepositSchedule
						depositsSchedule={ account.deposits_schedule }
					/>
				</CardBody>
			) }

			{ /* Notices */ }
			<CardBody className="wcpay-deposits-overview__notices__container">
				{ account?.deposits_blocked ? (
					<SuspendedDepositNotice />
				) : (
					<>
						{ ! hasCompletedWaitingPeriod && (
							<NewAccountWaitingPeriodNotice />
						) }
						{ hasCompletedWaitingPeriod &&
							isDepositAwaitingPendingFunds && (
								<NoFundsAvailableForDepositNotice />
							) }
						{ isNegativeBalanceDepositsPaused && (
							<NegativeBalanceDepositsPausedNotice />
						) }
						{ hasErroredExternalAccount && (
							<DepositFailureNotice
								updateAccountLink={
									wcpaySettings.accountStatus.accountLink
								}
							/>
						) }
						{ availableFunds > 0 &&
							! isAboveMinimumDepositAmount && (
								<DepositMinimumBalanceNotice
									minimumDepositAmountFormatted={ formatExplicitCurrency(
										minimumDepositAmount,
										selectedCurrency
									) }
								/>
							) }
					</>
				) }
			</CardBody>

			{ hasRecentDeposits && (
				<>
					<CardBody className="wcpay-deposits-overview__heading">
						<span className="wcpay-deposits-overview__heading__title">
							{ __( 'Payout history', 'woocommerce-payments' ) }
						</span>
					</CardBody>
					<RecentDepositsList deposits={ deposits } />
				</>
			) }

			{ ( hasRecentDeposits || canChangeDepositSchedule ) && (
				<CardFooter className="wcpay-deposits-overview__footer">
					{ hasRecentDeposits && (
						<Button
							variant="secondary"
							onClick={ navigateToDepositsHistory }
							__next40pxDefaultSize
						>
							{ __(
								'View full payout history',
								'woocommerce-payments'
							) }
						</Button>
					) }

					{ canChangeDepositSchedule && (
						<Button
							variant="tertiary"
							href={
								getAdminUrl( {
									page: 'wc-settings',
									tab: 'checkout',
									section: 'woocommerce_payments',
								} ) + '#payout-schedule'
							}
							onClick={ () =>
								recordEvent(
									'wcpay_overview_deposits_change_schedule_click'
								)
							}
							__next40pxDefaultSize
						>
							{ __(
								'Change payout schedule',
								'woocommerce-payments'
							) }
						</Button>
					) }
				</CardFooter>
			) }
		</OverviewCard>
	);
};

export default DepositsOverview;
