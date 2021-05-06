/* eslint-disable camelcase */
/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader, CardBody } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useDepositsOverview } from 'data';
import Loadable from 'components/loadable';

const DepositsInformationLoading = (): any => {
	return (
		<Card>
			<CardHeader>Deposits overview</CardHeader>
			<CardBody>
				<Loadable
					isLoading={ true }
					display="inline"
					placeholder="Deposit schedule placeholder"
				>
					{ 'foo' }
				</Loadable>
			</CardBody>
		</Card>
	);
};

type OverviewProps = {
	overview: AccountOverview.Overview;
};

/**
 * Renders a deposits overview
 *
 * @param {AccountOverview.Overview} props Deposits overview
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositsInformationOverview: React.FunctionComponent< OverviewProps > = (
	props
) => {
	const { overview }: OverviewProps = props;
	return (
		<Card>
			<CardHeader>{ 'foo' }</CardHeader>
			<CardBody>{ 'foobar' }</CardBody>
		</Card>
	);
};

const DepositsInformation = (): JSX.Element => {
	const {
		overview,
		isLoading,
	}: AccountOverview.OverviewResponse = useDepositsOverview();

	return isLoading ? (
		<DepositsInformationLoading />
	) : (
		<DepositsInformationOverview overview={ overview } />
	);
};

export default DepositsInformation;
