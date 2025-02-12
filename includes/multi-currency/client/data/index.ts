/** @format */

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import { initStore } from './store';

initStore();

// eslint-disable-next-line @typescript-eslint/naming-convention
export const WCPAY_STORE_NAME = STORE_NAME;

// We only ask for hooks when importing directly from 'multi-currency/data'.
import * as selectors from './selectors';
import * as actions from './actions';
import * as resolvers from './resolvers';

export { selectors, actions, resolvers };
export * from './hooks';
