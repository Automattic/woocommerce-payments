/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

const FraudProtectionHelpText = ( { level } ) => {
	const isHighProtectionLevel = 'high' === level;

	return (
		<p className={ 'fraud-protection__text--help-text' }>
			{ isHighProtectionLevel
				? __(
						'Offers the highest level of filtering for stores, ' +
							'but may catch some legitimate transactions.',
						'woocommerce-payments'
				  )
				: __(
						"Provides a standard level of filtering that's suitable for most businesses.",
						'woocommerce-payments'
				  ) }
		</p>
	);
};

export default FraudProtectionHelpText;
