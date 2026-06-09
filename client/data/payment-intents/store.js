/** @format */

/**
 * External dependencies
 */
import { createReduxStore, register, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import * as resolvers from './resolvers';
import { PAYMENT_INTENTS_STORE_NAME } from '../store-names';

export const STORE_NAME = PAYMENT_INTENTS_STORE_NAME;

// The reducer is nested under its slice key so selectors keep reading
// `state.paymentIntents.…`, exactly as they did under the old combined store.
export const store = createReduxStore( STORE_NAME, {
	reducer: combineReducers( { paymentIntents: reducer } ),
	actions,
	selectors,
	resolvers,
	controls,
} );

register( store );
