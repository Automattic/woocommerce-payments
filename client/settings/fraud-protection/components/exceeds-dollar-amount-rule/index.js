/**
 * External dependencies
 */
import React from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

const USDollarComponent = ( { level } ) => {
	const isHighProtectionLevel = 'high' === level;

	if ( isHighProtectionLevel ) {
		return (
			<li>
				{ interpolateComponents( {
					mixedString: __(
						'An order exceeds {{strong}}$1,000.00{{/strong}}.',
						'woocommerce-payments'
					),
					components: { strong: <strong /> },
				} ) }
			</li>
		);
	}

	return (
		<li>
			{ interpolateComponents( {
				mixedString: __(
					'An order exceeds {{strong}}$1,000.00{{/strong}} or {{strong}}10 items.{{/strong}}',
					'woocommerce-payments'
				),
				components: { strong: <strong /> },
			} ) }
		</li>
	);
};

const NotUSDollarComponent = ( { level, storeCurrency } ) => {
	const isHighProtectionLevel = 'high' === level;

	if ( isHighProtectionLevel ) {
		return (
			<li>
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							'An order exceeds the equivalent of {{strong}}$1,000.00 USD{{/strong}} in %s.',
							'woocommerce-payments'
						),
						storeCurrency.name
					),
					components: { strong: <strong /> },
				} ) }
			</li>
		);
	}

	return (
		<li>
			{ interpolateComponents( {
				mixedString: sprintf(
					__(
						'An order exceeds the equivalent of {{strong}}$1,000.00 USD{{/strong}} in %s or {{strong}}10 items.{{/strong}}',
						'woocommerce-payments'
					),
					storeCurrency.name
				),
				components: { strong: <strong /> },
			} ) }
		</li>
	);
};

const ExceedsDollarAmountRule = ( { level, storeCurrency } ) => {
	const isDefaultCurrencyUSD = 'USD' === storeCurrency.code;

	if ( isDefaultCurrencyUSD ) {
		return <USDollarComponent level={ level } />;
	}

	return (
		<NotUSDollarComponent level={ level } storeCurrency={ storeCurrency } />
	);
};

export default ExceedsDollarAmountRule;
