/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

// DEMO ONLY: fails on purpose to exercise the QIT Slack summary.
test.describe( 'Slack alert demo', () => {
	test( 'fails on purpose', () => {
		expect( 1 ).toBe( 2 );
	} );
} );
