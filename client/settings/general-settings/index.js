/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { useDevMode, useIsWCPayEnabled, useTestMode } from 'wcpay/data';
import CardBody from '../card-body';
import InlineNotice from 'wcpay/components/inline-notice';
import SetupLivePaymentsModal from 'wcpay/overview/modal/setup-live-payments';
import TestModeConfirmationModal from './test-mode-confirm-modal';

const GeneralSettings = () => {
	const [ isWCPayEnabled, setIsWCPayEnabled ] = useIsWCPayEnabled();
	const [ isEnabled, updateIsTestModeEnabled ] = useTestMode();
	const [ modalVisible, setModalVisible ] = useState( false );
	const isDevModeEnabled = useDevMode();
	const [ testModeModalVisible, setTestModeModalVisible ] = useState( false );

	return (
		<>
			<Card>
				<CardBody>
					<CheckboxControl
						checked={ isWCPayEnabled }
						onChange={ setIsWCPayEnabled }
						label={ sprintf(
							/* translators: %s: WooPayments */
							__( 'Enable %s', 'woocommerce-payments' ),
							'WooPayments'
						) }
						help={ sprintf(
							/* translators: %s: WooPayments */
							__(
								'When enabled, payment methods powered by %s will appear on checkout.',
								'woocommerce-payments'
							),
							'WooPayments'
						) }
					/>
					{ ! isDevModeEnabled && (
						<>
							<h4>
								{ __( 'Test mode', 'woocommerce-payments' ) }
							</h4>
							<CheckboxControl
								checked={ isEnabled }
								onChange={ ( enableTestMode ) => {
									if ( enableTestMode ) {
										setTestModeModalVisible( true );
									} else {
										updateIsTestModeEnabled(
											enableTestMode
										);
									}
								} }
								label={ __(
									'Enable test mode',
									'woocommerce-payments'
								) }
								help={ interpolateComponents( {
									mixedString: __(
										'Use {{testCardHelpLink}}test card numbers{{/testCardHelpLink}} to simulate ' +
											'various transactions. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
										'woocommerce-payments'
									),
									components: {
										testCardHelpLink: (
											// eslint-disable-next-line jsx-a11y/anchor-has-content
											<a
												target="_blank"
												rel="noreferrer"
												/* eslint-disable-next-line max-len */
												href="https://woo.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards"
											/>
										),
										learnMoreLink: (
											// eslint-disable-next-line jsx-a11y/anchor-has-content
											<a
												target="_blank"
												rel="noreferrer"
												href="https://woo.com/document/woopayments/testing-and-troubleshooting/testing/"
											/>
										),
									},
								} ) }
							/>
						</>
					) }
					{ isDevModeEnabled && (
						<InlineNotice
							status="warning"
							isDismissible={ false }
							actions={ [
								{
									label: __(
										'Set up payments',
										'woocommerce-payments'
									),
									variant: 'secondary',
									onClick: () => {
										setModalVisible( true );
									},
								},
							] }
							className="wcpay-general-settings__notice"
						>
							<span>
								{ interpolateComponents( {
									mixedString: sprintf(
										/* translators: %s: WooPayments */
										__(
											'{{b}}%1$s is in dev mode.{{/b}} You need to set up a live %1$s account before ' +
												'you can accept real transactions. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
											'woocommerce-payments'
										),
										'WooPayments'
									),
									components: {
										b: <b />,
										learnMoreLink: (
											// eslint-disable-next-line jsx-a11y/anchor-has-content
											<a
												target="_blank"
												rel="noreferrer"
												href="https://woo.com/document/woopayments/testing-and-troubleshooting/dev-mode/"
											/>
										),
									},
								} ) }
							</span>
						</InlineNotice>
					) }
				</CardBody>
			</Card>
			{ modalVisible && (
				<SetupLivePaymentsModal
					closeModal={ () => setModalVisible( false ) }
				/>
			) }
			{ testModeModalVisible && (
				<TestModeConfirmationModal
					onClose={ () => {
						setTestModeModalVisible( false );
					} }
					onConfirm={ () => {
						updateIsTestModeEnabled( true );
						setTestModeModalVisible( false );
					} }
				/>
			) }
		</>
	);
};

export default GeneralSettings;
