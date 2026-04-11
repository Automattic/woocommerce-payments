/*
 * External dependencies
 */
import { registerStore } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import * as multiCurrency from './';
import reducer from './reducer';

// Extracted into wrapper function to facilitate testing.
export const initStore = () =>
	registerStore( STORE_NAME, {
		reducer,
		actions: {
			...multiCurrency.actions,
		},
		controls,
		selectors: {
			...multiCurrency.selectors,
		},
		resolvers: {
			...multiCurrency.resolvers,
		},
	} );
