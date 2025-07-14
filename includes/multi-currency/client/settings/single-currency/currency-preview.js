/** @format */
/**
 * External dependencies
 */
import React, { useCallback, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from 'wcpay/components/wp-components-wrapped/components/card';
import { CardBody } from 'wcpay/components/wp-components-wrapped/components/card-body';
import { TextControlWithAffixes } from '@woocommerce/components';
import {
	formatCurrency,
	isZeroDecimalCurrency,
} from 'multi-currency/utils/currency';

const CurrencyPreview = ( {
	storeCurrency,
	targetCurrency,
	currencyRate = null, // Manual rate value.
	roundingValue,
	charmValue,
} ) => {
	const [ baseValue, setBaseValue ] = useState( 20.0 );
	const [ calculatedValue, setCalculatedValue ] = useState( '0' );

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
						isZeroDecimalCurrency( targetCurrency.code )
							? charmed
							: charmed * 100,
						targetCurrency.code,
						null,
						true
				  );
		},
		[ charmValue, currencyRate, roundingValue, targetCurrency ]
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
		<Card className="single-currency-settings-preview-wrapper">
			<CardBody className="wcpay-card-body">
				<div>
					{ storeCurrency.symbol_position === 'left' ? (
						<TextControlWithAffixes
							label={ storeCurrency.name }
							prefix={ storeCurrency.symbol }
							data-testid="store_currency_value"
							value={ baseValue.toString() }
							onChange={ handleTextControlChange }
						/>
					) : (
						<TextControlWithAffixes
							label={ storeCurrency.name }
							suffix={ storeCurrency.symbol }
							data-testid="store_currency_value"
							value={ baseValue.toString() }
							onChange={ handleTextControlChange }
						/>
					) }
				</div>
				<div>
					<TextControlWithAffixes
						data-testid="calculated_value"
						label={ targetCurrency && targetCurrency.name }
						value={ calculatedValue }
						onChange={ () => null }
						disabled
					/>
				</div>
			</CardBody>
		</Card>
	);
};

export default CurrencyPreview;
