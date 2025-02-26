/** @format */

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import { initStore } from './store';

initStore();

// eslint-disable-next-line @typescript-eslint/naming-convention
export const WCPAY_STORE_NAME = STORE_NAME;
