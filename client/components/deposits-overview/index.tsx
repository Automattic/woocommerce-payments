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
import DepositsOverviewFooter from './footer';

const DepositsOverview = (): JSX.Element => {
	const { overview, isLoading } = useSelectedCurrencyOverview();

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
