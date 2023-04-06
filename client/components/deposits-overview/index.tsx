/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import strings from './strings';
import NextDepositDetails from './next-deposit';
import RecentDepositsList from './recent-deposits-list';
import DepositsOverviewFooter from './footer';
import DepositOverviewSectionHeading from './section-heading';
import DepositSchedule from './deposit-schedule';
import SuspendedDepositNotice from './suspended-deposit-notice';

const DepositsOverview = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies, account } = overviews;

	const overview = currencies[ 0 ]; // TODO: To handle multiple currencies we'll need to fetch the currently selected currency.
	const currency = 'usd'; // TODO: hardcoded curency for recent deposits.
	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>

			{ /* Only show the next deposit section if the page is loading or if deposits are not blocked. */ }
			{ ( isLoading || ! account.deposits_blocked ) && (
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

			<RecentDepositsList currency={ currency } />


			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
