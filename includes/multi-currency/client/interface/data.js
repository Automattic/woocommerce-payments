/**
 * Dependencies from WooPayments to MCCY.
 */
// wcpay/data — import from the specific slice so MCCY consumers don't pull in
// the entire data layer.
export { useSettings, useMultiCurrency } from 'wcpay/data/settings';

/**
 * Dependencies from MCCY to WooPayments.
 */
export { useCurrencies, useEnabledCurrencies } from 'multi-currency/data';
