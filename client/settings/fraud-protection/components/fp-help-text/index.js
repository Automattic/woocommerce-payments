/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

const getFraudProtectionLevelText = function ( level ) {
	switch ( level ) {
		case 'high':
			return __(
				'Offers the highest level of filtering for stores, but may catch some legitimate transactions.',
				'woocommerce-payments'
			);
		case 'advanced':
			return __(
				'Allows you to fine-tune the level of filtering according to your business needs.',
				'woocommerce-payments'
			);
		default:
			return __(
				"Provides a standard level of filtering that's suitable for most businesses.",
				'woocommerce-payments'
			);
	}
};

const FraudProtectionHelpText = ( { level } ) => {
	return (
		<p className={ 'fraud-protection__text--help-text' }>
			{ getFraudProtectionLevelText( level ) }
		</p>
	);
};

export default FraudProtectionHelpText;
