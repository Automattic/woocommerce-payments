/**
 * External dependencies
 */
import React from 'react';
import { sprintf, __ } from '@wordpress/i18n';

const USDollarComponent = ( { level } ) => {
	const isHighProtectionLevel = 'high' === level;

	if ( isHighProtectionLevel ) {
		return (
			<li>
				{ __( 'An order exceeds ', 'woocommerce-payments' ) }{ ' ' }
				<strong>{ __( '$1,000.00.', 'woocommerce-payments' ) }</strong>
			</li>
		);
	}

	return (
		<li>
			{ __( 'An order exceeds', 'woocommerce-payments' ) }{ ' ' }
			<strong>{ __( '$1,000.00', 'woocommerce-payments' ) } </strong>
			{ __( 'or', 'woocommerce-payments' ) }{ ' ' }
			<strong>{ __( '10 items.', 'woocommerce-payments' ) }</strong>
		</li>
	);
};

const NotUSDollarComponent = ( { level, storeCurrency } ) => {
	const isHighProtectionLevel = 'high' === level;

	if ( isHighProtectionLevel ) {
		return (
			<li>
				{ __(
					'An order exceeds the equivalent of',
					'woocommerce-payments'
				) }{ ' ' }
				<strong>
					{ __( '$1,000.00 USD', 'woocommerce-payments' ) }
				</strong>{ ' ' }
				{ sprintf(
					__( 'in %s.', 'woocommerce-payments' ),
					storeCurrency.name
				) }
			</li>
		);
	}

	return (
		<li>
			{ __(
				'An order exceeds the equivalent of',
				'woocommerce-payments'
			) }{ ' ' }
			<strong>{ __( '$1,000.00 USD', 'woocommerce-payments' ) } </strong>{ ' ' }
			{ sprintf(
				__( 'in %s', 'woocommerce-payments' ),
				storeCurrency.name
			) }{ ' ' }
			{ __( 'or', 'woocommerce-payments' ) }{ ' ' }
			<strong>{ __( '10 items.', 'woocommerce-payments' ) }</strong>
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
