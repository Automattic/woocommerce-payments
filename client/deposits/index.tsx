/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice } from 'components/test-mode-notice';
import BannerNotice from 'components/banner-notice';
import DepositSchedule from 'components/deposits-overview/deposit-schedule';
import { useAllDepositsOverviews } from 'data';
import DepositsList from './list';

const useNextDepositNoticeState = () => {
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ isDismissed, setIsDismissed ] = useState(
		wcpaySettings.isNextDepositNoticeDismissed
	);

	const setNextDepositNoticeDismissed = () => {
		setIsDismissed( true );
		wcpaySettings.isNextDepositNoticeDismissed = true;
		updateOptions( {
			wcpay_next_deposit_notice_dismissed: true,
		} );
	};

	return {
		isNextDepositNoticeDismissed: isDismissed,
		handleDismissNextDepositNotice: setNextDepositNoticeDismissed,
	};
};

const NextDepositNotice: React.FC = () => {
	const {
		overviews: { account },
	} = useAllDepositsOverviews();
	const {
		isNextDepositNoticeDismissed,
		handleDismissNextDepositNotice,
	} = useNextDepositNoticeState();

	const isDepositsUnrestricted =
		wcpaySettings.accountStatus.deposits?.restrictions ===
		'deposits_unrestricted';

	const hasCompletedWaitingPeriod =
		wcpaySettings.accountStatus.deposits?.completed_waiting_period;

	if (
		! isDepositsUnrestricted ||
		! hasCompletedWaitingPeriod ||
		! account ||
		isNextDepositNoticeDismissed
	) {
		return null;
	}

	return (
		<BannerNotice
			status="info"
			isDismissible
			onRemove={ handleDismissNextDepositNotice }
		>
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
