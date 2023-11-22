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

const GeneralSettings = () => {
	const [ isWCPayEnabled, setIsWCPayEnabled ] = useIsWCPayEnabled();
	const [ isEnabled, updateIsTestModeEnabled ] = useTestMode();
	const [ modalVisible, setModalVisible ] = useState( false );
	const isDevModeEnabled = useDevMode();

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
					<h4>{ __( 'Test mode', 'woocommerce-payments' ) }</h4>
					<CheckboxControl
						checked={ isDevModeEnabled || isEnabled }
						disabled={ isDevModeEnabled }
						onChange={ updateIsTestModeEnabled }
						label={ __(
							'Enable test mode',
							'woocommerce-payments'
						) }
						help={ interpolateComponents( {
							mixedString: __(
								'Use {{testCardHelpLink}}test card numbers{{/testCardHelpLink}} to simulate various transactions. ' +
									'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
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
										'{{b}}%s is in dev mode.{{/b}} You need to finish setting up %s before you can ' +
											'turn off test mode to accept live transactions. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
										'woocommerce-payments'
									),
									'WooPayments',
									'WooPayments'
								),
								components: {
									b: <b />,
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
						</span>
					</InlineNotice>
				</CardBody>
			</Card>
			{ modalVisible && (
				<SetupLivePaymentsModal
					closeModal={ () => setModalVisible( false ) }
				/>
			) }
		</>
	);
};

export default GeneralSettings;
