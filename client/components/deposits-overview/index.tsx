/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import RecentDepositsList from './recent-deposits-list';
import DepositSchedule from './deposit-schedule';
import {
	DepositTransitDaysNotice,
	NegativeBalanceDepositsPausedNotice,
	NewAccountWaitingPeriodNotice,
	SuspendedDepositNotice,
} from './deposit-notices';
import DepositsOverviewFooter from './footer';
import useRecentDeposits from './hooks';
import './style.scss';

const DepositsOverview: React.FC = () => {
	const {
		account,
		overview,
		isLoading: isLoadingOverview,
	} = useSelectedCurrencyOverview();
	const selectedCurrency =
		overview?.currency || wcpaySettings.accountDefaultCurrency;
	const { isLoading: isLoadingDeposits, deposits } = useRecentDeposits(
		selectedCurrency
	);

	const hasNextDeposit = !! overview?.nextScheduled;
	const hasCompletedWaitingPeriod =
		wcpaySettings.accountStatus.deposits?.completed_waiting_period;
	const isNegativeBalanceDepositsPaused =
		overview?.available && overview.available.amount < 0;
	// TODO: Find a condition for rendering the loan notice.
	// const { includesFinancingPayout } = useDepositIncludesLoan(
	// 	nextDeposit.id
	// );
	const isLoading = isLoadingOverview || isLoadingDeposits;

	// This card isn't shown if there are no deposits, so we can bail early.
	if ( ! hasNextDeposit && ! isLoading && deposits.length === 0 ) {
		return <></>;
	}

	return (
		<Card className="wcpay-deposits-overview">
			<CardHeader>
				{ __( 'Deposits', 'woocommerce-payments' ) }
			</CardHeader>

			{ /* Deposit schedule message */ }
			{ ! isLoading && hasNextDeposit && !! account && (
				<CardBody>
					<DepositSchedule { ...account.deposits_schedule } />
				</CardBody>
			) }

			{ /* Notices */ }
			{ ! isLoading && (
				<CardBody
					className={ 'wcpay-deposits-overview__notices__container' }
				>
					{ account?.deposits_blocked ? (
						<SuspendedDepositNotice />
					) : (
						<>
							<DepositTransitDaysNotice />
							{ /* includesFinancingPayout && <DepositIncludesLoanPayoutNotice /> */ }
							{ ! hasCompletedWaitingPeriod && (
								<NewAccountWaitingPeriodNotice />
							) }
							{ isNegativeBalanceDepositsPaused && (
								<NegativeBalanceDepositsPausedNotice />
							) }
						</>
					) }
				</CardBody>
			) }

			{ /* Only show the deposit history section if the page is finished loading and there are deposits. */ }
			{ ! isLoading &&
				!! account &&
				!! deposits &&
				deposits.length > 0 &&
				! account?.deposits_blocked && (
					<>
						<CardBody className="wcpay-deposits-overview__heading">
							<span className="wcpay-deposits-overview__heading__title">
								<Loadable
									isLoading={ isLoading }
									value={ __(
										'Deposit history',
										'woocommerce-payments'
									) }
								/>
							</span>
						</CardBody>
						<RecentDepositsList deposits={ deposits } />
					</>
				) }
			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
