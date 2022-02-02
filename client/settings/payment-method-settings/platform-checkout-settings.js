/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { usePlatformCheckoutEnabledSettings } from 'wcpay/data';

const PlatformCheckoutSettings = () => {
	const [
		isPlatformCheckoutEnabled,
		updateIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	return (
		<Card>
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
			</CardBody>
		</Card>
	);
};

export default PlatformCheckoutSettings;
