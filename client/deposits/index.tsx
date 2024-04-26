/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { useDispatch } from '@wordpress/data';
import { ExternalLink } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

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
import { recordEvent } from 'wcpay/tracks';

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

const useAccountStatus = () => {
	const {
		overviews: { account },
	} = useAllDepositsOverviews();

	const hasErroredExternalAccount =
		account?.default_external_accounts?.some(
			( externalAccount ) => externalAccount.status === 'errored'
		) ?? false;

	return {
		account,
		hasErroredExternalAccount,
	};
};

const NextDepositNotice: React.FC = () => {
	const { account, hasErroredExternalAccount } = useAccountStatus();
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
		! hasScheduledDeposits ||
		hasErroredExternalAccount
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
	const { hasErroredExternalAccount } = useAccountStatus();
	const accountLink = addQueryArgs( wcpaySettings.accountStatus.accountLink, {
		source: 'deposit__failure-notice',
	} );

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
						<ExternalLink
							onClick={ () =>
								recordEvent(
									'wcpay_account_details_link_clicked',
									{ source: 'deposit__failure-notice' }
								)
							}
							href={ accountLink }
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
