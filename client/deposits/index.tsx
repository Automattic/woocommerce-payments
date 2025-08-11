/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
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
import { MaybeShowMerchantFeedbackPrompt } from 'wcpay/merchant-feedback-prompt';
import { saveOption } from 'wcpay/data/settings/actions';

const useNextDepositNoticeState = () => {
	const [ isDismissed, setIsDismissed ] = useState(
		wcpaySettings.isNextDepositNoticeDismissed
	);

	const setNextDepositNoticeDismissed = () => {
		setIsDismissed( true );
		wcpaySettings.isNextDepositNoticeDismissed = true;
		saveOption( 'wcpay_next_deposit_notice_dismissed', true );
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

	const accountLink = wcpaySettings.accountStatus.accountLink
		? addQueryArgs( wcpaySettings.accountStatus.accountLink, {
				from: 'WCPAY_PAYOUTS',
				source: 'wcpay-payout-failure-notice',
		  } )
		: '';

	return hasErroredExternalAccount && accountLink !== '' ? (
		<BannerNotice
			status="warning"
			icon
			className="deposit-failure-notice"
			isDismissible={ false }
		>
			{ interpolateComponents( {
				mixedString: __(
					'Payouts are currently paused because a recent payout failed. Please {{updateLink/}}.',
					'woocommerce-payments'
				),
				components: {
					updateLink: (
						<ExternalLink
							onClick={ () =>
								recordEvent(
									'wcpay_account_details_link_clicked',
									{
										from: 'WCPAY_PAYOUTS',
										source: 'wcpay-payout-failure-notice',
									}
								)
							}
							href={ accountLink }
						>
							{ __(
								'update your bank account details',
								'woocommerce-payments'
							) }
						</ExternalLink>
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
			<MaybeShowMerchantFeedbackPrompt />
			<TestModeNotice currentPage="deposits" />
			<NextDepositNotice />
			<DepositFailureNotice />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
