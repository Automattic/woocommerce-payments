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
import DepositsOverviewFooter from './footer';
import DepositOverviewSectionHeading from './section-heading';
import { areDepositsBlocked } from './utils';
import DepositSchedule from './deposit-schedule';
import SuspendedDepositNotice from './suspended-deposit-notice';

const DepositsOverview = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies, account } = overviews;

	const overview = currencies[ 0 ]; // TODO: To handle multiple currencies we'll need to fetch the currently selected currency.

	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>

			{ /* Only show the next deposit section if the page is loading or if deposits are not blocked. */ }
			{ ( isLoading || ! areDepositsBlocked( account ) ) && (
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
				( areDepositsBlocked( account ) ? (
					<DepositOverviewSectionHeading
						title={ strings.depositHistory.title }
						children={ <SuspendedDepositNotice /> }
					/>
				) : (
					<DepositOverviewSectionHeading
						title={ strings.depositHistory.title }
						text={
							<DepositSchedule { ...account.deposits_schedule } />
						}
					/>
				) ) }

			<p>Deposit history table will go here</p>

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
