/** @format */

/**
 * Internal dependencies
 */
// Each slice registers its own independent store on first use. Importing a hook
// from here pulls in only that slice's store, so a chunk that uses one slice
// does not bundle the other 14. (There is no longer a single combined store to
// initialize — that is what let every chunk drag in all slices.)
export * from './deposits/hooks';
export * from './transactions/hooks';
export * from './charges/hooks';
export * from './timeline/hooks';
export * from './disputes/hooks';
export * from './settings/hooks';
export * from './card-readers/hooks';
export * from './capital/hooks';
export * from './documents/hooks';
export * from './payment-intents/hooks';
export * from './authorizations/hooks';
export * from './files/hooks';
export * from './pm-promotions/hooks';
export * from './dispute-readiness/hooks';
export * from './reports/hooks';
