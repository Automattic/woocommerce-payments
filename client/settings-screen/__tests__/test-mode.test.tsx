/**
 * Internal dependencies
 */
import { getFieldParts } from '../field-parts';

describe( 'is_test_mode_enabled', () => {
	beforeAll( () => {
		( global as Record< string, unknown > ).wcpaySettingsScreenConfig = {
			fraudRulesUrl: '',
		};
	} );

	const parts = () => getFieldParts().is_test_mode_enabled;

	it( 'is locked on for a test account', () => {
		const item = {
			is_test_mode_onboarding: true,
			is_test_mode_enabled: false,
		};

		expect( parts().getValue?.( { item } ) ).toBe( true );
		expect( parts().isDisabled?.( { item } ) ).toBe( true );
		expect( getFieldParts().test_account_notice.isVisible?.( item ) ).toBe(
			true
		);
	} );

	it( 'follows the saved setting for other accounts', () => {
		const item = {
			is_test_mode_onboarding: false,
			is_test_mode_enabled: false,
		};

		expect( parts().getValue?.( { item } ) ).toBe( false );
		expect( parts().isDisabled?.( { item } ) ).toBe( false );
		expect( getFieldParts().test_account_notice.isVisible?.( item ) ).toBe(
			false
		);
	} );
} );
