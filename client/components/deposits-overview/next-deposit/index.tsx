import * as React from 'react';
import { CardBody } from '@wordpress/components';

interface OverviewProps {
	overview: AccountOverview.Overview;
	account: AccountOverview.Account;
	isLoading: boolean;
}

const NextDepositDetails: React.FunctionComponent< OverviewProps > = (
	props
) => {
	return (
		<CardBody>
			Next Deposit section
		</CardBody>
	);
};

export default NextDepositDetails;