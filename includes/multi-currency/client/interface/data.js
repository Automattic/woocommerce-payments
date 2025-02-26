/**
 * Dependencies from WooPayments to MCCY.
 */
// wcpay/data
import 'wcpay/data';
export { useSettings, useMultiCurrency } from 'wcpay/data/settings/hooks';

/**
 * Dependencies from MCCY to WooPayments.
 */
export { useCurrencies, useEnabledCurrencies } from 'multi-currency/data';
