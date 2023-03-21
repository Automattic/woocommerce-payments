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

interface OverviewProps {
	overview?: AccountOverview.Overview;
	account?: AccountOverview.Account;
	isLoading: boolean;
}

/**
 * Renders a deposits overview
 *
 * @param {OverviewProps} props Deposits overview
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositsOverviewDetails: React.FunctionComponent< OverviewProps > = ( {
	isLoading,
} ) => {
	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>
			<NextDepositDetails isLoading={ isLoading } />

			<p>Deposits History Section Goes here</p>

			<p>Deposits Card Footer/Action Goes here</p>
		</Card>
	);
};

const DepositsOverview = (): JSX.Element => {
	const { account, overview, isLoading } = useSelectedCurrencyOverview();

	if ( isLoading ) {
		return <DepositsOverviewDetails isLoading={ isLoading } />;
	}

	return (
		<>
			<DepositsOverviewDetails
				account={ account }
				overview={ overview }
				isLoading={ isLoading }
			/>
		</>
	);
};

export default DepositsOverview;
