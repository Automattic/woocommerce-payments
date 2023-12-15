/**
 * External dependencies
 */
import { test } from '@playwright/test';

/**
 * Sets the shopper as the authenticated user for a test or test suite.
 */
export const useShopper = (): void => {
	test.use( {
		storageState: process.env.CUSTOMER_STATE,
	} );
};

/**
 * Sets the merchant as the authenticated user for a test or test suite.
 */
export const useMerchant = (): void => {
	test.use( {
		storageState: process.env.MERCHANT_STATE,
	} );
};
