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
import RecentDeposits from './recent-deposits';

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
			<NextDepositDetails isLoading={ isLoading } overview={ overview } />

			<RecentDeposits
				currency={ currency }
				account={ overviews.account }
			/>
		</Card>
	);
};

export default DepositsOverview;
