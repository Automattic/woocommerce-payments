/** @format **/

/**
 * External dependencies
 */
import {
	default as OriginalCurrencyFactory,
	SymbolPosition,
	CountryInfo,
	CurrencyConfig,
	CurrencyProps,
} from '@woocommerce/currency';
import { numberFormat } from '@woocommerce/number';
import { sprintf } from '@wordpress/i18n';

export type Currency = {
	code: string;
	symbol: string;
	symbolPosition: string;
	decimalSeparator: string;
	priceFormat: string;
	thousandSeparator: string;
	precision: number;
	negativeFormat: string;
};

export type { SymbolPosition, CountryInfo, CurrencyConfig, CurrencyProps };

export function getCurrencyData(): any {
	return wcpaySettings ? wcpaySettings.currencyData : {};
}

/**
 *
 * @param {CurrencyConfig} currencySetting
 * @return {Object} currency object
 */
const CurrencyFactory = function ( currencySetting?: CurrencyConfig ): any {
	const original = OriginalCurrencyFactory( currencySetting );
	let currency: Currency;

	function setCurrency( setting?: CurrencyConfig ) {
		const defaultCurrency = {
			code: 'USD',
			symbol: '$',
			symbolPosition: 'left' as const,
			thousandSeparator: ',',
			decimalSeparator: '.',
			precision: 2,
			negativeFormat: '-',
		};
		const config = { ...defaultCurrency, ...setting };

		let precision = config.precision;
		if ( precision === null ) {
			// eslint-disable-next-line no-console
			console.warn( 'Currency precision is null' );
			// eslint-enable-next-line no-console

			precision = NaN;
		} else if ( typeof precision === 'string' ) {
			precision = parseInt( precision, 10 );
		}

		currency = {
			code: config.code.toString(),
			symbol: config.symbol.toString(),
			symbolPosition: config.symbolPosition.toString(),
			decimalSeparator: config.decimalSeparator.toString(),
			priceFormat: original.getPriceFormat( config ),
			thousandSeparator: config.thousandSeparator.toString(),
			precision,
			negativeFormat: config.negativeFormat.toString(),
		};
	}

	/**
	 * Formats money value.
	 *
	 * @param {number|string} number          number to format
	 * @param {boolean}       [useCode=false] Set to `true` to use the currency code instead of the symbol.
	 * @return {?string} A formatted string.
	 */
	function formatAmount( number: number | string, useCode = false ) {
		if ( typeof number === 'string' ) {
			number = parseFloat( number );
		}
		const isNegative: boolean = number < 0;
		number = isNegative ? -number : number;

		const formattedNumber = numberFormat( currency, number );

		if ( formattedNumber === '' ) {
			return formattedNumber;
		}

		const { priceFormat, symbol, code, negativeFormat } = currency;

		// eslint-disable-next-line @wordpress/valid-sprintf
		if ( isNegative ) {
			const formattedPrice = sprintf(
				priceFormat,
				useCode ? code : symbol,
				formattedNumber
			);
			switch ( negativeFormat ) {
				case '-':
					return `-${ formattedPrice }`;
				case '()':
					return `(${ formattedPrice })`;
				case 'o-':
					return sprintf(
						priceFormat,
						useCode ? code : symbol,
						'-' + formattedNumber
					);
			}
		}
		return sprintf( priceFormat, useCode ? code : symbol, formattedNumber );
	}

	setCurrency( currencySetting );

	return {
		getCurrencyConfig: original.getCurrencyConfig.bind( CurrencyFactory ),
		getDataForCountry: original.getDataForCountry.bind( CurrencyFactory ),
		setCurrency: setCurrency,
		formatAmount: formatAmount,
		formatCurrency: original.formatCurrency.bind( CurrencyFactory ),
		getPriceFormat: original.getPriceFormat.bind( CurrencyFactory ),
		formatDecimal: original.formatDecimal.bind( CurrencyFactory ),
		formatDecimalString: original.formatDecimalString.bind(
			CurrencyFactory
		),
		render: original.render.bind( CurrencyFactory ),
		usingOverride: true,
	};
};

export default CurrencyFactory;
