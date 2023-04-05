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

const DepositsOverview = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies } = overviews;

	const overview = currencies[ 0 ]; // TODO: To handle multiple currencies we'll need to fetch the currently selected currency.

	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>
			<NextDepositDetails isLoading={ isLoading } overview={ overview } />

			<p>Deposits History Section Goes here</p>

			<p>Deposits Card Footer/Action Goes here</p>

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
