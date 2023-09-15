/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { useDevMode, useIsWCPayEnabled, useTestMode } from 'wcpay/data';
import CardBody from '../card-body';

const GeneralSettings = () => {
	const [ isWCPayEnabled, setIsWCPayEnabled ] = useIsWCPayEnabled();
	const [ isEnabled, updateIsTestModeEnabled ] = useTestMode();
	const isDevModeEnabled = useDevMode();

	return (
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
					label={
						isDevModeEnabled
							? __(
									'Dev mode is active so all transactions will be in test mode. ' +
										'This setting is only available to live accounts.',
									'woocommerce-payments'
							  )
							: __( 'Enable test mode', 'woocommerce-payments' )
					}
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
									href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards"
								/>
							),
							learnMoreLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/"
								/>
							),
						},
					} ) }
				/>
			</CardBody>
		</Card>
	);
};

export default GeneralSettings;
