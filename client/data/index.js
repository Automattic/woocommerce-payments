/** @format */

/*
 * External dependencies
 */
import { registerStore, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import * as transactions from './transactions';
import * as charges from './charges';

registerStore( STORE_NAME, {
	reducer: combineReducers( {
		transactions: transactions.reducer,
		charges: charges.reducer,
	} ),
	actions: {
		...transactions.actions,
		...charges.actions,
	},
	controls,
	selectors: {
		...transactions.selectors,
		...charges.selectors,
	},
	resolvers: {
		...transactions.resolvers,
		...charges.resolvers,
	},
} );

export const WCPAY_STORE_NAME = STORE_NAME;

export * from './transactions';
export * from './charges';
