/*
 * External dependencies
 */
import { registerStore, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import * as deposits from './deposits';
import * as transactions from './transactions';
import * as charges from './charges';
import * as timeline from './timeline';
import * as disputes from './disputes';
<<<<<<< HEAD
import * as settings from './settings';
=======
>>>>>>> 971f3b8a (Create data store for multi shopper currency feature (#1856))
import * as multiCurrency from './multi-currency';

// Extracted into wrapper function to facilitate testing.
export const initStore = () =>
	registerStore( STORE_NAME, {
		reducer: combineReducers( {
			deposits: deposits.reducer,
			transactions: transactions.reducer,
			charges: charges.reducer,
			timeline: timeline.reducer,
			disputes: disputes.reducer,
			settings: settings.reducer,
			multiCurrency: multiCurrency.reducer,
		} ),
		actions: {
			...deposits.actions,
			...transactions.actions,
			...charges.actions,
			...timeline.actions,
			...disputes.actions,
			...settings.actions,
			...multiCurrency.actions,
		},
		controls,
		selectors: {
			...deposits.selectors,
			...transactions.selectors,
			...charges.selectors,
			...timeline.selectors,
			...disputes.selectors,
			...settings.selectors,
			...multiCurrency.selectors,
		},
		resolvers: {
			...deposits.resolvers,
			...transactions.resolvers,
			...charges.resolvers,
			...timeline.resolvers,
			...disputes.resolvers,
			...settings.resolvers,
			...multiCurrency.resolvers,
		},
	} );
