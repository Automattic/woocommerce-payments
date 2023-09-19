/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ProtectionLevel } from '../../advanced-settings/constants';

const getFraudProtectionLevelText = function ( level: string ) {
	switch ( level ) {
		case ProtectionLevel.HIGH:
			return __(
				'Offers the highest level of filtering for stores, but may catch some legitimate transactions.',
				'woocommerce-payments'
			);
		case ProtectionLevel.ADVANCED:
			return __(
				'Allows you to fine-tune the level of filtering according to your business needs.',
				'woocommerce-payments'
			);
		case ProtectionLevel.STANDARD:
			return __(
				"Provides a standard level of filtering that's suitable for most businesses.",
				'woocommerce-payments'
			);
		case ProtectionLevel.BASIC:
			return __(
				'Provides the base level of platform protection.',
				'woocommerce-payments'
			);
		default:
			return '';
	}
};

interface FraudProtectionHelpTextProps {
	level: string;
}

const FraudProtectionHelpText: React.FC< FraudProtectionHelpTextProps > = ( {
	level,
} ) => {
	return (
		<p className={ 'fraud-protection__text--help-text' }>
			{ getFraudProtectionLevelText( level ) }
		</p>
	);
};

export default FraudProtectionHelpText;
