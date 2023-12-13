/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice } from 'components/test-mode-notice';
import BannerNotice from 'components/banner-notice';
import DepositSchedule from 'components/deposits-overview/deposit-schedule';
import { useAllDepositsOverviews } from 'data';
import DepositsList from './list';

const NextDepositNotice: React.FC = () => {
	const {
		overviews: { account },
	} = useAllDepositsOverviews();

	const isDepositsUnrestricted =
		wcpaySettings.accountStatus.deposits?.restrictions ===
		'deposits_unrestricted';

	if ( ! isDepositsUnrestricted || ! account ) {
		return null;
	}

	return (
		<BannerNotice status="info" isDismissible={ false }>
			<DepositSchedule depositsSchedule={ account.deposits_schedule } />
		</BannerNotice>
	);
};

const DepositsPage: React.FC = () => {
	return (
		<Page>
			<TestModeNotice currentPage="deposits" />
			<NextDepositNotice />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
