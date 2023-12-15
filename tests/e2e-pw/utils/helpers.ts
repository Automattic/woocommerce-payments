/**
 * External dependencies
 */
import path from 'path';
import { test } from '@playwright/test';

export const merchantStorageFile = path.resolve(
	__dirname,
	'../.auth/merchant.json'
);

export const customerStorageFile = path.resolve(
	__dirname,
	'../.auth/customer.json'
);

/**
 * Sets the shopper as the authenticated user for a test or test suite.
 */
export const useShopper = (): void => {
	test.use( {
		storageState: customerStorageFile,
	} );
};

/**
 * Sets the merchant as the authenticated user for a test or test suite.
 */
export const useMerchant = (): void => {
	test.use( {
		storageState: merchantStorageFile,
	} );
};
