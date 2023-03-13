import * as React from 'react';
import { CardBody } from '@wordpress/components';

interface OverviewProps {
	overview: AccountOverview.Overview;
	account: AccountOverview.Account;
	isLoading: boolean;
}

const DepositsOverviewActions: React.FunctionComponent< OverviewProps > = (
	props
) => {
	return (
		<CardBody>
			Deposit Actions/Footer section
		</CardBody>
	);
};

export default DepositsOverviewActions;