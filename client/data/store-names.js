/** @format */

/**
 * Per-slice @wordpress/data store names.
 *
 * This module is intentionally dependency-free. `store.js` (which calls
 * `register()`) imports its name from here, and so do the slice's resolvers and
 * actions — that way resolvers/actions never import `store.js`, which would
 * create a `store.js → resolvers → store.js` cycle that breaks when a resolver
 * or action module is imported on its own (e.g. in a unit test).
 */

export const DEPOSITS_STORE_NAME = 'wc/payments/deposits';
export const TRANSACTIONS_STORE_NAME = 'wc/payments/transactions';
export const CHARGES_STORE_NAME = 'wc/payments/charges';
export const TIMELINE_STORE_NAME = 'wc/payments/timeline';
export const DISPUTES_STORE_NAME = 'wc/payments/disputes';
export const SETTINGS_STORE_NAME = 'wc/payments/settings';
export const READERS_STORE_NAME = 'wc/payments/readers';
export const CAPITAL_STORE_NAME = 'wc/payments/capital';
export const DOCUMENTS_STORE_NAME = 'wc/payments/documents';
export const PAYMENT_INTENTS_STORE_NAME = 'wc/payments/paymentIntents';
export const AUTHORIZATIONS_STORE_NAME = 'wc/payments/authorizations';
export const FILES_STORE_NAME = 'wc/payments/files';
export const PM_PROMOTIONS_STORE_NAME = 'wc/payments/pmPromotions';
export const DISPUTE_READINESS_STORE_NAME = 'wc/payments/disputeReadiness';
export const REPORTS_STORE_NAME = 'wc/payments/reports';
