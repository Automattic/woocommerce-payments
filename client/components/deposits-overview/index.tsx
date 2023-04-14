/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import strings from './strings';
import NextDepositDetails from './next-deposit';
import RecentDepositsList from './recent-deposits-list';
import DepositsOverviewFooter from './footer';
import DepositOverviewSectionHeading from './section-heading';
import DepositSchedule from './deposit-schedule';
import SuspendedDepositNotice from './suspended-deposit-notice';
import './style.scss';

const DepositsOverview = (): JSX.Element => {
	const { account, overview, isLoading } = useSelectedCurrencyOverview();

	return (
		<Card className="wcpay-deposits-overview">
			<CardHeader>{ strings.heading }</CardHeader>

			{ /* Only show the next deposit section if the page is loading or if deposits are not blocked. */ }
			{ ( isLoading || ! account?.deposits_blocked ) && (
				<>
					<DepositOverviewSectionHeading
						title={ strings.nextDeposit.title }
						text={ strings.nextDeposit.description }
						isLoading={ isLoading }
					/>
					<NextDepositDetails
						isLoading={ isLoading }
						overview={ overview }
					/>
				</>
			) }

			{ ! isLoading &&
				!! account &&
				( account.deposits_blocked ? (
					<DepositOverviewSectionHeading
						title={ strings.depositHistoryHeading }
						children={ <SuspendedDepositNotice /> }
					/>
				) : (
					<DepositOverviewSectionHeading
						title={ strings.depositHistoryHeading }
						text={
							<DepositSchedule { ...account.deposits_schedule } />
						}
					/>
				) ) }

			{ overview?.currency && (
				<RecentDepositsList currency={ overview.currency } />
			) }

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
