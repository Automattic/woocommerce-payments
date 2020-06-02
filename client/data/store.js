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

// Extracted into wrapper function to facilitate testing.
export const initStore = () => registerStore( STORE_NAME, {
	reducer: combineReducers( {
		deposits: deposits.reducer,
		transactions: transactions.reducer,
		charges: charges.reducer,
		timeline: timeline.reducer,
	} ),
	actions: {
		...deposits.actions,
		...transactions.actions,
		...charges.actions,
		...timeline.actions,
	},
	controls,
	selectors: {
		...deposits.selectors,
		...transactions.selectors,
		...charges.selectors,
		...timeline.selectors,
	},
	resolvers: {
		...deposits.resolvers,
		...transactions.resolvers,
		...charges.resolvers,
		...timeline.resolvers,
	},
} );

