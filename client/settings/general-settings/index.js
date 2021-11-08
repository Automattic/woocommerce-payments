/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

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
					label={ __(
						'Enable WooCommerce Payments',
						'woocommerce-payments'
					) }
					help={ __(
						'When enabled, payment methods powered by WooCommerce Payments will appear on checkout.',
						'woocommerce-payments'
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
									href="https://woocommerce.com/document/payments/testing/#test-cards"
								/>
							),
							learnMoreLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://woocommerce.com/document/payments/testing/"
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
