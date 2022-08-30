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
import * as settings from './settings';
import * as multiCurrency from './multi-currency';
import * as readers from './card-readers';
import * as capital from './capital';
import * as documents from './documents';
import * as paymentIntents from './payment-intents';
import * as authorizations from './authorizations';

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
			readers: readers.reducer,
			capital: capital.reducer,
			documents: documents.reducer,
			paymentIntents: paymentIntents.reducer,
			authorizations: authorizations.reducer,
		} ),
		actions: {
			...deposits.actions,
			...transactions.actions,
			...charges.actions,
			...timeline.actions,
			...disputes.actions,
			...settings.actions,
			...multiCurrency.actions,
			...readers.actions,
			...capital.actions,
			...documents.actions,
			...paymentIntents.actions,
			...authorizations.actions,
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
			...readers.selectors,
			...capital.selectors,
			...documents.selectors,
			...paymentIntents.selectors,
			...authorizations.selectors,
		},
		resolvers: {
			...deposits.resolvers,
			...transactions.resolvers,
			...charges.resolvers,
			...timeline.resolvers,
			...disputes.resolvers,
			...settings.resolvers,
			...multiCurrency.resolvers,
			...readers.resolvers,
			...capital.resolvers,
			...documents.resolvers,
			...paymentIntents.resolvers,
			...authorizations.resolvers,
		},
	} );
