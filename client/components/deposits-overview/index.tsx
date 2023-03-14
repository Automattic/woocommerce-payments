/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import strings from './strings';

interface OverviewProps {
	overview: AccountOverview.Overview | null | undefined;
	account: AccountOverview.Account | null | undefined;
	isLoading: boolean;
}

/**
 * Renders a deposits overview
 *
 * @param {AccountOverview.Overview} props Deposits overview
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositsOverviewDetails: React.FunctionComponent< OverviewProps > = (
	props
) => {
	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>

			<p>Next Deposits Section Goes here</p>

			<p>Deposits History Section Goes here</p>

			<p>Deposits Card Footer/Action Goes here</p>
		</Card>
	);
};

const DepositsOverview = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies, account } = overviews;

	if ( isLoading ) {
		return (
			<DepositsOverviewDetails
				overview={ null }
				account={ null }
				isLoading={ isLoading }
			/>
		);
	}

	const overview = currencies[ 0 ]; // TODO: To handle multiple currencies we'll need to fetch the currently selected currency.

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
