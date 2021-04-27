/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import './style.scss';

const TestModeSettings = () => {
	const [ isEnabled, setIsEnabled ] = useState( false );

	return (
		<Card className="test-mode-settings">
			<CardBody size="large">
				<h4>{ __( 'Test mode', 'woocommerce-payments' ) }</h4>
				<CheckboxControl
					checked={ isEnabled }
					onChange={ setIsEnabled }
					label={ __(
						'Enable test mode for payments on the store',
						'woocommerce-payments'
					) }
					help={ interpolateComponents( {
						mixedString: __(
							// eslint-disable-next-line max-len
							"When enabled, you'll be able to test how your customers pay for orders on your store. Use {{testCardHelpLink}}test card numbers{{/testCardHelpLink}} to simulate various transactions. {{learnMoreLink}}Learn more{{/learnMoreLink}}.",
							'woocommerce-payments'
						),
						components: {
							testCardHelpLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://docs.woocommerce.com/document/payments/testing/#test-cards"
								/>
							),
							learnMoreLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://docs.woocommerce.com/document/payments/testing/"
								/>
							),
						},
					} ) }
				/>
			</CardBody>
		</Card>
	);
};

export default TestModeSettings;
