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
import { getDepositScheduleDescription } from './utils';

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
			<DepositOverviewSectionHeading
				title={ strings.nextDeposit.title }
				description={ strings.nextDeposit.description }
				isLoading={ isLoading }
			/>
			<NextDepositDetails isLoading={ isLoading } overview={ overview } />

			{ ! isLoading && (
				<DepositOverviewSectionHeading
					title={ strings.depositHistory.title }
					description={ getDepositScheduleDescription( account ) }
				/>
			) }

			<p>Deposits Card Footer/Action Goes here</p>

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
