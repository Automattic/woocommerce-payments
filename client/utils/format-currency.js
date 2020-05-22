/** @format **/

/**
 * External dependencies
 */
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { keyBy } from 'lodash';

const currencyByCode = keyBy(
	getCurrencyData(),
	currency => currency.code.toLowerCase()
);

const getCurrency = ( code ) => Currency( currencyByCode[ code ] );

export default getCurrency;
