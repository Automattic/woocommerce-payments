/** @format */
/**
 * External dependencies
 */
import React, { useCallback, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { TextControlWithAffixes } from '@woocommerce/components';
import { formatCurrency } from 'wcpay/utils/currency';

const CurrencyPreview = ( {
	storeCurrency,
	targetCurrency,
	currencyRate = null, // Manual rate value.
	roundingValue,
	charmValue,
} ) => {
	const [ baseValue, setBaseValue ] = useState( 20.0 );
	const [ calculatedValue, setCalculatedValue ] = useState( 0 );

	const calculateCurrencyConversion = useCallback(
		( value ) => {
			const amount = parseFloat( value.toString().replace( /,/g, '.' ) );
			const converted =
				amount *
				parseFloat( currencyRate ? currencyRate : targetCurrency.rate );
			const rounded = parseFloat( roundingValue )
				? Math.ceil( converted / parseFloat( roundingValue ) ) *
				  parseFloat( roundingValue )
				: converted;
			const charmed = rounded + parseFloat( charmValue );
			return isNaN( charmed )
				? __( 'Please enter a valid number', 'woocommerce-payments' )
				: formatCurrency(
						charmed * 100,
						targetCurrency.code,
						storeCurrency.code
				  );
		},
		[
			charmValue,
			currencyRate,
			roundingValue,
			targetCurrency,
			storeCurrency,
		]
	);

	useEffect( () => {
		if ( targetCurrency ) {
			const initialCalculation = calculateCurrencyConversion( baseValue );
			setCalculatedValue( initialCalculation );
		}
	}, [
		calculateCurrencyConversion,
		baseValue,
		targetCurrency,
		roundingValue,
		charmValue,
		currencyRate,
	] );

	const handleTextControlChange = ( value ) => {
		setBaseValue( value );
		const calculatedNewValue = calculateCurrencyConversion( value );
		setCalculatedValue( calculatedNewValue );
	};

	return (
		<div className={ 'single-currency-settings-preview-wrapper' }>
			<Card>
				<CardBody>
					<div>
						<h4>{ storeCurrency.name }</h4>
						{ 'left' === storeCurrency.symbol_position ? (
							<TextControlWithAffixes
								prefix={ storeCurrency.symbol }
								data-testid="store_currency_value"
								value={ baseValue.toString() }
								onChange={ handleTextControlChange }
							/>
						) : (
							<TextControlWithAffixes
								suffix={ storeCurrency.symbol }
								data-testid="store_currency_value"
								value={ baseValue.toString() }
								onChange={ handleTextControlChange }
							/>
						) }
					</div>
					<div>
						<h4>{ targetCurrency && targetCurrency.name }</h4>
						<div>
							<strong data-testid="calculated_value">
								{ calculatedValue }
							</strong>
						</div>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default CurrencyPreview;
