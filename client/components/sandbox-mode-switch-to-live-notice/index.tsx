/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import BannerNotice from '../banner-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import { recordEvent } from 'wcpay/tracks';
import { ClickTooltip } from 'wcpay/components/tooltip';
import ErrorBoundary from 'wcpay/components/error-boundary';
import DevModeMessage from 'wcpay/components/dev-mode-message';
import SetupLivePaymentsModal from './modal';
import './style.scss';
import {
	hasSandboxAccount,
	hasTestAccount,
	isInDevMode,
	isInTestMode,
} from 'wcpay/utils';

interface Props {
	from: string;
	source: string;
}

const SandboxModeSwitchToLiveNotice: React.FC< Props > = ( {
	from,
	source,
} ) => {
	const [ livePaymentsModalVisible, setLivePaymentsModalVisible ] =
		useState( false );

	const handleCtaClick = () => {
		recordEvent( 'wcpay_setup_live_payments_modal_open', {
			from,
			source,
		} );

		setLivePaymentsModalVisible( true );
	};

	// A live account is forced into test mode while development mode is on, e.g. on a staging copy of a live store.
	// The wcpay_test_mode filter can still turn test mode off, and then payments are real, so check it too.
	const hasLiveAccountInDevMode =
		isInDevMode() &&
		isInTestMode() &&
		!! wcpaySettings?.isAccountConnected &&
		!! wcpaySettings?.accountStatus?.isLive;

	if (
		! hasTestAccount() &&
		! hasSandboxAccount() &&
		! hasLiveAccountInDevMode
	) {
		return null;
	}

	let devModeAccountType = 'live';
	if ( hasTestAccount() ) {
		devModeAccountType = 'test';
	} else if ( hasSandboxAccount() ) {
		devModeAccountType = 'sandbox';
	}

	return (
		<>
			<BannerNotice
				status="warning"
				className="sandbox-mode-notice"
				isDismissible={ false }
			>
				{ hasTestAccount() &&
					! isInDevMode() &&
					interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								// eslint-disable-next-line max-len
								"{{div}}{{strong}}You're using a test account.{{/strong}} To accept payments from shoppers, {{switchToLiveLink}}activate your %1$s account.{{/switchToLiveLink}}{{/div}}{{learnMoreIcon/}}",
								'woocommerce-payments'
							),
							'WooPayments'
						),
						components: {
							div: <div />,
							strong: <strong />,
							learnMoreIcon: (
								<ClickTooltip
									buttonIcon={ <HelpOutlineIcon /> }
									buttonLabel={ __(
										'Learn more about test accounts',
										'woocommerce-payments'
									) }
									maxWidth={ '250px' }
									content={
										<>
											{ interpolateComponents( {
												mixedString: sprintf(
													/* translators: 1: WooPayments */
													__(
														// eslint-disable-next-line max-len
														'A test account gives you access to all %1$s features while checkout transactions are simulated. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
														'woocommerce-payments'
													),
													'WooPayments'
												),
												components: {
													learnMoreLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<Link
															href={
																// eslint-disable-next-line max-len
																'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/'
															}
															target="_blank"
															rel="noreferrer noopener"
															type="external"
															onClick={ () =>
																recordEvent(
																	'wcpay_overview_sandbox_mode_learn_more_clicked',
																	{
																		account_type:
																			'test',
																		is_dev_mode: false,
																	}
																)
															}
														/>
													),
												},
											} ) }
										</>
									}
								/>
							),
							switchToLiveLink: (
								<Button
									variant="link"
									onClick={ handleCtaClick }
									__next40pxDefaultSize
								/>
							),
						},
					} ) }
				{ hasSandboxAccount() &&
					! isInDevMode() &&
					interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								// eslint-disable-next-line max-len
								"{{div}}{{strong}}You're using a sandbox test account.{{/strong}} To accept real payments from shoppers, you will need to first {{resetAccountLink}}reset your account{{/resetAccountLink}} and, then, provide additional details about your business.{{/div}}{{learnMoreIcon/}}",
								'woocommerce-payments'
							),
							'WooPayments'
						),
						components: {
							div: <div />,
							strong: <strong />,
							resetAccountLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<Link
									href={
										'https://woocommerce.com/document/woopayments/startup-guide/#resetting'
									}
									target="_blank"
									rel="noreferrer"
									type="external"
								/>
							),
							learnMoreIcon: (
								<ClickTooltip
									buttonIcon={ <HelpOutlineIcon /> }
									buttonLabel={ __(
										'Learn more about sandbox accounts',
										'woocommerce-payments'
									) }
									maxWidth={ '250px' }
									content={
										<>
											{ interpolateComponents( {
												mixedString: sprintf(
													/* translators: 1: WooPayments */
													__(
														// eslint-disable-next-line max-len
														'A sandbox account gives you access to all %1$s features while checkout transactions are simulated. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
														'woocommerce-payments'
													),
													'WooPayments'
												),
												components: {
													learnMoreLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<Link
															href={
																// eslint-disable-next-line max-len
																'https://woocommerce.com/document/woopayments/startup-guide/#signup-process'
															}
															target="_blank"
															rel="noreferrer"
															type="external"
															onClick={ () =>
																recordEvent(
																	'wcpay_overview_sandbox_mode_learn_more_clicked',
																	{
																		account_type:
																			'sandbox',
																		is_dev_mode: false,
																	}
																)
															}
														/>
													),
												},
											} ) }
										</>
									}
								/>
							),
						},
					} ) }
				{ isInDevMode() && (
					<div>
						<DevModeMessage
							onLearnMoreClick={ () =>
								recordEvent(
									'wcpay_overview_sandbox_mode_learn_more_clicked',
									{
										account_type: devModeAccountType,
										is_dev_mode: true,
									}
								)
							}
						/>
					</div>
				) }
			</BannerNotice>
			{ livePaymentsModalVisible && (
				<ErrorBoundary>
					<SetupLivePaymentsModal
						from={ from }
						source={ source }
						onClose={ () => setLivePaymentsModalVisible( false ) }
					/>
				</ErrorBoundary>
			) }
		</>
	);
};

export default SandboxModeSwitchToLiveNotice;
