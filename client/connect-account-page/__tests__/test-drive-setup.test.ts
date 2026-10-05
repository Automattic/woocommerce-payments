/** @format */
/**
 * Internal dependencies
 */
import { isTestDriveSetupDone } from '../test-drive-setup';

describe( 'isTestDriveSetupDone', () => {
	it( 'keeps waiting while the account is pending verification', () => {
		// A non-US account is pending for about 90 seconds while Stripe checks the identity document.
		expect( isTestDriveSetupDone( 'pending_verification', 90 ) ).toBe(
			false
		);
		expect( isTestDriveSetupDone( 'pending_verification', 150 ) ).toBe(
			false
		);
	} );

	it( 'keeps waiting while the account has no status yet', () => {
		expect( isTestDriveSetupDone( undefined, 30 ) ).toBe( false );
	} );

	it( 'finishes once the account is complete', () => {
		expect( isTestDriveSetupDone( 'complete', 5 ) ).toBe( true );
	} );

	it( 'gives up waiting after 150 seconds', () => {
		expect( isTestDriveSetupDone( 'pending_verification', 151 ) ).toBe(
			true
		);
	} );
} );
