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

const DepositsOverview = (): JSX.Element => {
	const { account, overview, isLoading } = useSelectedCurrencyOverview();

	if ( isLoading ) {
		return <DepositsOverviewDetails isLoading={ isLoading } />;
	}

	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>
			<NextDepositDetails isLoading={ isLoading } overview={ overview } />

			<p>Deposits History Section Goes here</p>

			<p>Deposits Card Footer/Action Goes here</p>
		</Card>
	);
};

export default DepositsOverview;
