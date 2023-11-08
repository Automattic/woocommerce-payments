/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import NextDepositDetails from './next-deposit';
import RecentDepositsList from './recent-deposits-list';
import DepositSchedule from './deposit-schedule';
import SuspendedDepositNotice from './suspended-deposit-notice';
import DepositsOverviewFooter from './footer';
import DepositOverviewSectionHeading from './section-heading';
import useRecentDeposits from './hooks';
import './style.scss';

const DepositsOverview: React.FC = () => {
	const {
		account,
		overview,
		isLoading: isLoadingOverview,
	} = useSelectedCurrencyOverview();

	let currency = wcpaySettings.accountDefaultCurrency;

	if ( overview?.currency ) {
		currency = overview.currency;
	}

	const { isLoading: isLoadingDeposits, deposits } = useRecentDeposits(
		currency
	);

	const hasNextDeposit = !! overview?.nextScheduled;

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
			{ /* Only show the next deposit section if the page is loading or if deposits are not blocked. */ }
			{ ( isLoading || ! account?.deposits_blocked ) && (
				<>
					<DepositOverviewSectionHeading
						title={ __( 'Next deposit', 'woocommerce-payments' ) }
						text={ __(
							'The amount may change while payments are still accumulating',
							'woocommerce-payments'
						) }
						isLoading={ isLoading }
					/>
					<NextDepositDetails
						isLoading={ isLoading }
						overview={ overview }
					/>
				</>
			) }

			{ /* Notices */ }
			<CardBody>
				{ account?.deposits_blocked && <SuspendedDepositNotice /> }
			</CardBody>

			{ /* Only show the deposit history section if the page is finished loading and there are deposits. */ }
			{ ! isLoading &&
				!! account &&
				!! deposits &&
				deposits.length > 0 &&
				! account?.deposits_blocked && (
					<>
						<DepositOverviewSectionHeading
							title={ __(
								'Deposit history',
								'woocommerce-payments'
							) }
							text={
								<DepositSchedule
									{ ...account.deposits_schedule }
								/>
							}
						/>
						<RecentDepositsList deposits={ deposits } />
					</>
				) }
			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
