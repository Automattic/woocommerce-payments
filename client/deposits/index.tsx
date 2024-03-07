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
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { TestModeNotice } from 'components/test-mode-notice';
import BannerNotice from 'components/banner-notice';
import DepositSchedule from 'components/deposits-overview/deposit-schedule';
import { useAllDepositsOverviews } from 'data';
import { useSettings } from 'wcpay/data';
import DepositsList from './list';
import { hasAutomaticScheduledDeposits } from 'wcpay/deposits/utils';

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

	const hasScheduledDeposits = hasAutomaticScheduledDeposits(
		account?.deposits_schedule.interval
	);

	if (
		! isDepositsUnrestricted ||
		! hasCompletedWaitingPeriod ||
		! account ||
		isNextDepositNoticeDismissed ||
		! hasScheduledDeposits
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

const DepositFailureNotice: React.FC = () => {
	const {
		overviews: { account },
	} = useAllDepositsOverviews();

	const hasErroredExternalAccount =
		account?.external_accounts?.some(
			( externalAccount ) => externalAccount.status === 'errored'
		) ?? false;

	return hasErroredExternalAccount ? (
		<BannerNotice
			status="warning"
			icon
			className="deposit-failure-notice"
			isDismissible={ false }
		>
			{ interpolateComponents( {
				mixedString: __(
					'Deposits are currently paused because a recent deposit failed. Please {{updateLink}}update your bank account details{{/updateLink}}.',
					'woocommerce-payments'
				),
				components: {
					updateLink: (
						// Link content is in the format string above.
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							target="_blank"
							rel="noopener noreferrer"
							href="https://woo.com/document/woopayments/deposits/change-deposit-account-info/"
						/>
					),
				},
			} ) }
		</BannerNotice>
	) : null;
};

const DepositsPage: React.FC = () => {
	// pre-fetching the settings.
	useSettings();

	return (
		<Page>
			<TestModeNotice currentPage="deposits" />
			<NextDepositNotice />
			<DepositFailureNotice />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
