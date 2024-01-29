/**
 * External dependencies
 */
import * as React from 'react';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getAdminUrl } from 'wcpay/utils';
import wcpayTracks from 'tracks';
import Loadable from 'components/loadable';
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import RecentDepositsList from './recent-deposits-list';
import DepositSchedule from './deposit-schedule';
import {
	DepositTransitDaysNotice,
	NegativeBalanceDepositsPausedNotice,
	NewAccountWaitingPeriodNotice,
	NoFundsAvailableForDepositNotice,
	SuspendedDepositNotice,
} from './deposit-notices';
import useRecentDeposits from './hooks';
import './style.scss';

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
	const { isLoading: isLoadingDeposits, deposits } = useRecentDeposits(
		selectedCurrency
	);

	const isLoading = isLoadingOverview || isLoadingDeposits;

	const availableFunds = overview?.available?.amount ?? 0;
	const pendingFunds = overview?.pending?.amount ?? 0;

	// If the available balance is negative, deposits may be paused.
	const isNegativeBalanceDepositsPaused = availableFunds < 0;
	// When there are funds pending but no available funds, deposits are paused.
	const isDepositAwaitingPendingFunds =
		availableFunds === 0 && pendingFunds > 0;
	const hasCompletedWaitingPeriod =
		wcpaySettings.accountStatus.deposits?.completed_waiting_period;
	const canChangeDepositSchedule =
		! account?.deposits_blocked && hasCompletedWaitingPeriod;
	// Only show the deposit history section if the page is finished loading and there are deposits. */ }
	const hasRecentDeposits = ! isLoading && deposits?.length > 0 && !! account;

	// Show a loading state if the page is still loading.
	if ( isLoading ) {
		return (
			<Card className="wcpay-deposits-overview">
				<CardHeader>
					{ __( 'Deposits', 'woocommerce-payments' ) }
				</CardHeader>

				<CardBody className="wcpay-deposits-overview__schedule__container">
					<Loadable
						isLoading
						placeholder={
							<DepositSchedule
								depositsSchedule={ {
									delay_days: 0,
									interval: 'daily',
									monthly_anchor: 1,
									weekly_anchor: 'monday',
								} }
							/>
						}
					/>
				</CardBody>
			</Card>
		);
	}

	if (
		! hasCompletedWaitingPeriod &&
		availableFunds === 0 &&
		pendingFunds === 0
	) {
		// If still in new account waiting period and account has no transactions,
		// don't render deposits card (nothing to show).
		return null;
	}

	return (
		<Card className="wcpay-deposits-overview">
			<CardHeader>
				{ __( 'Deposits', 'woocommerce-payments' ) }
			</CardHeader>

			{ /* Deposit schedule message */ }
			{ isDepositsUnrestricted && !! account && (
				<CardBody className="wcpay-deposits-overview__schedule__container">
					<DepositSchedule
						depositsSchedule={ account.deposits_schedule }
						showNextDepositDate={ availableFunds > 0 }
					/>
				</CardBody>
			) }

			{ /* Notices */ }
			<CardBody className="wcpay-deposits-overview__notices__container">
				{ account?.deposits_blocked ? (
					<SuspendedDepositNotice />
				) : (
					<>
						{ isDepositsUnrestricted &&
							! isDepositAwaitingPendingFunds && (
								<DepositTransitDaysNotice />
							) }
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
					</>
				) }
			</CardBody>

			{ hasRecentDeposits && (
				<>
					<CardBody className="wcpay-deposits-overview__heading">
						<span className="wcpay-deposits-overview__heading__title">
							{ __( 'Deposit history', 'woocommerce-payments' ) }
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
							href={ getAdminUrl( {
								page: 'wc-admin',
								path: '/payments/deposits',
							} ) }
							onClick={ () =>
								wcpayTracks.recordEvent(
									wcpayTracks.events
										.OVERVIEW_DEPOSITS_VIEW_HISTORY_CLICK
								)
							}
						>
							{ __(
								'View full deposits history',
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
								} ) + '#deposit-schedule'
							}
							onClick={ () =>
								wcpayTracks.recordEvent(
									wcpayTracks.events
										.OVERVIEW_DEPOSITS_CHANGE_SCHEDULE_CLICK
								)
							}
						>
							{ __(
								'Change deposit schedule',
								'woocommerce-payments'
							) }
						</Button>
					) }
				</CardFooter>
			) }
		</Card>
	);
};

export default DepositsOverview;
