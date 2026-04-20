/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { useDevMode, useTestMode, useTestModeOnboarding } from 'wcpay/data';
import CardBody from '../card-body';
import SetupLivePaymentsModal from 'wcpay/components/sandbox-mode-switch-to-live-notice/modal';
import TestModeConfirmationModal from './test-mode-confirm-modal';
import EnableWooPaymentsCheckbox from './enable-woopayments-checkbox';
import { recordEvent } from 'wcpay/tracks';

const GeneralSettings = () => {
	const [ isEnabled, updateIsTestModeEnabled ] = useTestMode();
	const [ modalVisible, setModalVisible ] = useState( false );
	const isTestModeOnboarding = useTestModeOnboarding();
	const isDevModeEnabled = useDevMode();
	const [ testModeModalVisible, setTestModeModalVisible ] = useState( false );

	useEffect( () => {
		const handleActivatePayments = () => {
			recordEvent( 'wcpay_settings_setup_live_payments_click', {
				source: 'wcadmin-settings-page',
			} );

			setModalVisible( true );
		};

		document.addEventListener(
			'wcpay:activate_payments',
			handleActivatePayments
		);

		return () => {
			document.removeEventListener(
				'wcpay:activate_payments',
				handleActivatePayments
			);
		};
	}, [] );

	return (
		<>
			<Card>
				<CardBody className="wcpay-card-body">
					<EnableWooPaymentsCheckbox />
					{ ! isTestModeOnboarding && (
						<>
							<h4>
								{ __( 'Test mode', 'woocommerce-payments' ) }
							</h4>
							<CheckboxControl
								checked={ isDevModeEnabled || isEnabled }
								disabled={ isDevModeEnabled }
								onChange={ ( enableTestMode ) => {
									if ( enableTestMode ) {
										setTestModeModalVisible( true );
									} else {
										recordEvent(
											'wcpay_test_mode_disabled',
											{
												source: 'wcadmin-settings-page',
											}
										);

										updateIsTestModeEnabled( false );
									}
								} }
								label={
									isDevModeEnabled
										? __(
												'Enable test mode (enabled by development mode)',
												'woocommerce-payments'
										  )
										: __(
												'Enable test mode',
												'woocommerce-payments'
										  )
								}
								help={
									isDevModeEnabled
										? interpolateComponents( {
												/* eslint-disable max-len */
												mixedString: __(
													'Test mode is active because your store is running in a development or staging environment. ' +
														'To disable it, switch to a production {{wpEnvLink}}WordPress environment{{/wpEnvLink}} or remove the WCPAY_DEV_MODE constant. ' +
														'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
													'woocommerce-payments'
												),
												/* eslint-enable max-len */
												components: {
													wpEnvLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<a
															target="_blank"
															rel="noreferrer"
															/* eslint-disable-next-line max-len */
															href="https://make.wordpress.org/core/2020/08/27/wordpress-environment-types/"
														/>
													),
													learnMoreLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<a
															target="_blank"
															rel="noreferrer"
															/* eslint-disable-next-line max-len */
															href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/"
														/>
													),
												},
										  } )
										: interpolateComponents( {
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
															href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards"
														/>
													),
													learnMoreLink: (
														// eslint-disable-next-line jsx-a11y/anchor-has-content
														<a
															target="_blank"
															rel="noreferrer"
															/* eslint-disable-next-line max-len */
															href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/"
														/>
													),
												},
										  } )
								}
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</CardBody>
			</Card>
			{ modalVisible && (
				<SetupLivePaymentsModal
					from="WCPAY_SETTINGS"
					source="wcadmin-settings-page"
					onClose={ () => {
						recordEvent( 'wcpay_setup_live_payments_modal_exit', {
							source: 'wcadmin-settings-page',
						} );

						setModalVisible( false );
					} }
				/>
			) }
			{ testModeModalVisible && (
				<TestModeConfirmationModal
					onClose={ () => {
						recordEvent( 'wcpay_test_mode_modal_exit', {
							source: 'wcadmin-settings-page',
						} );

						setTestModeModalVisible( false );
					} }
					onConfirm={ () => {
						recordEvent( 'wcpay_test_mode_enabled', {
							source: 'wcadmin-settings-page',
						} );

						updateIsTestModeEnabled( true );
						setTestModeModalVisible( false );
					} }
				/>
			) }
		</>
	);
};

export default GeneralSettings;
