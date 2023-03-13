/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Icon, moreVertical } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';

interface OverviewProps {
	overview: AccountOverview.Overview;
	account: AccountOverview.Account;
	isLoading: boolean;
}

/**
 * Renders a deposits card header dropDown icon
 *
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositsDetailsDropdownMenu = (): JSX.Element => {
	return (
		<Icon
			icon={ moreVertical }
			className="wcpay-deposits-overview-header__icon"
		/>
	);
};

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
			<CardHeader>
				{ __( 'Deposits', 'woocommerce-payments' ) }
				<DepositsDetailsDropdownMenu />
			</CardHeader>

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

	return (
		<React.Fragment>
			{ currencies.map( ( overview: AccountOverview.Overview ) => (
				<DepositsOverviewDetails
					key={ overview.currency }
					account={ account }
					overview={ overview }
					isLoading={ isLoading }
				/>
			) ) }
		</React.Fragment>
	);
};

export default DepositsOverview;
