/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { usePlatformCheckoutEnabledSettings } from 'wcpay/data';

const CUSTOM_MESSAGE_MAX_LENGTH = 24;

const PlatformCheckoutSettings = () => {
	const [
		isPlatformCheckoutEnabled,
		updateIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	return (
		<Card className="platform-checkout-settings">
			<CardBody>
				<CheckboxControl
					checked={ isPlatformCheckoutEnabled }
					onChange={ updateIsPlatformCheckoutEnabled }
					label={ __(
						'Enable Platform Checkout',
						'woocommerce-payments'
					) }
					help={ __(
						'When enabled, customers will be able to checkout using Platform Checkout.',
						'woocommerce-payments'
					) }
				/>
				<div className="platform-checkout-settings__custom-message-wrapper">
					<h4>{ __( 'Custom message', 'woocommerce-payments' ) }</h4>
					<TextControl
						help={ __(
							'Inform your customers about the return, refund, and exchange policy, or include any other useful message. ' +
								'Note that you can add plain text and links, but not images.',
							'woocommerce-payments'
						) }
						placeholder={ __(
							'Free returns and exchanges',
							'woocommerce-payments'
						) }
						onChange={ () => false }
						maxLength={ CUSTOM_MESSAGE_MAX_LENGTH }
					/>
					<span className="input-help-text" aria-hidden="true">
						{ `${ 1 } / ${ CUSTOM_MESSAGE_MAX_LENGTH }` }
					</span>
				</div>
			</CardBody>
		</Card>
	);
};

export default PlatformCheckoutSettings;
