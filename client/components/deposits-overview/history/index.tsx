import * as React from 'react';
import { CardBody } from '@wordpress/components';

interface OverviewProps {
	overview: AccountOverview.Overview;
	account: AccountOverview.Account;
	isLoading: boolean;
}

const DepositsHistory: React.FunctionComponent< OverviewProps > = (
	props
) => {
	return (
		<CardBody>
			Deposit History section
		</CardBody>
	);
};

export default DepositsHistory;