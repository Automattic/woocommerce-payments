/** @format */

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import { initStore } from './store';

initStore();

// eslint-disable-next-line @typescript-eslint/naming-convention
export const WCPAY_STORE_NAME = STORE_NAME;

// We only ask for hooks when importing directly from 'wcpay/data'.
export * from './deposits/hooks';
export * from './transactions/hooks';
export * from './charges/hooks';
export * from './timeline/hooks';
export * from './disputes/hooks';
export * from './settings/hooks';
export * from './multi-currency';
export * from './card-readers/hooks';
export * from './capital/hooks';
