/**
 * Internal dependencies
 */
import {
	getSettings,
	getIsWCPayEnabled,
	getEnabledPaymentMethodIds,
	getIsManualCaptureEnabled,
	getAccountStatementDescriptor,
	isSavingSettings,
	getPaymentRequestLocations,
	getIsPaymentRequestEnabled,
	getAccountBusinessName,
	getAccountBusinessURL,
	getAccountBusinessSupportAddress,
	getAccountBusinessSupportEmail,
	getAccountBusinessSupportPhone,
	getAccountBrandingLogo,
	getAccountBrandingIcon,
	getAccountBrandingPrimaryColor,
	getAccountBrandingSecondaryColor,
	getIsPlatformCheckoutEnabled,
	getPlatformCheckoutCustomMessage,
} from '../selectors';

describe( 'Settings selectors tests', () => {
	describe( 'getSettings()', () => {
		test( 'returns the value of state.settings.data', () => {
			const state = {
				settings: {
					data: {
						foo: 'bar',
					},
				},
			};

			expect( getSettings( state ) ).toEqual( { foo: 'bar' } );
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getSettings( state ) ).toEqual( {} );
			}
		);
	} );

	describe( 'getIsWCPayEnabled()', () => {
		test( 'returns the value of state.settings.data.is_wcpay_enabled', () => {
			const state = {
				settings: {
					data: {
						is_wcpay_enabled: true,
					},
				},
			};

			expect( getIsWCPayEnabled( state ) ).toBeTruthy();
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getIsWCPayEnabled( state ) ).toBeFalsy();
		} );
	} );

	describe( 'getEnabledPaymentMethodIds()', () => {
		test( 'returns the value of state.settings.data.enabled_payment_method_ids', () => {
			const state = {
				settings: {
					data: {
						enabled_payment_method_ids: [ 'foo', 'bar' ],
					},
				},
			};

			expect( getEnabledPaymentMethodIds( state ) ).toEqual( [
				'foo',
				'bar',
			] );
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns [] if missing (tested state: %j)', ( state ) => {
			expect( getEnabledPaymentMethodIds( state ) ).toEqual( [] );
		} );
	} );

	describe( 'isSavingSettings()', () => {
		test( 'returns the value of state.settings.isSaving', () => {
			const state = {
				settings: {
					isSaving: true,
				},
			};

			expect( isSavingSettings( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isSavingSettings( state ) ).toBeFalsy();
			}
		);
	} );

	describe( 'getIsManualCaptureEnabled()', () => {
		test( 'returns the value of state.settings.data.is_manual_capture_enabled', () => {
			const state = {
				settings: {
					data: {
						is_manual_capture_enabled: true,
					},
				},
			};

			expect( getIsManualCaptureEnabled( state ) ).toBeTruthy();
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getIsManualCaptureEnabled( state ) ).toBeFalsy();
		} );
	} );

	describe( 'getAccountStatementDescriptor()', () => {
		test( 'returns the value of state.settings.data.account_statement_descriptor', () => {
			const state = {
				settings: {
					data: {
						account_statement_descriptor: 'my account statement',
					},
				},
			};

			expect( getAccountStatementDescriptor( state ) ).toEqual(
				'my account statement'
			);
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getAccountStatementDescriptor( state ) ).toEqual( '' );
		} );
	} );

	describe( 'getIsPaymentRequestEnabled()', () => {
		test( 'returns the value of state.settings.data.is_payment_request_enabled', () => {
			const state = {
				settings: {
					data: {
						is_payment_request_enabled: true,
					},
				},
			};

			expect( getIsPaymentRequestEnabled( state ) ).toBeTruthy();
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getIsPaymentRequestEnabled( state ) ).toBeFalsy();
		} );
	} );

	describe( 'getPaymentRequestLocations()', () => {
		test( 'returns the value of state.settings.data.payment_request_enabled_locations', () => {
			const state = {
				settings: {
					data: {
						payment_request_enabled_locations: [
							'product',
							'cart',
						],
					},
				},
			};

			expect( getPaymentRequestLocations( state ) ).toEqual( [
				'product',
				'cart',
			] );
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns [] if missing (tested state: %j)', ( state ) => {
			expect( getPaymentRequestLocations( state ) ).toEqual( [] );
		} );
	} );

	describe( 'getIsPlatformCheckoutEnabled()', () => {
		test( 'returns the value of state.settings.data.is_platform_checkout_enabled', () => {
			const state = {
				settings: {
					data: {
						is_platform_checkout_enabled: true,
					},
				},
			};

			expect( getIsPlatformCheckoutEnabled( state ) ).toBeTruthy();
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getIsPlatformCheckoutEnabled( state ) ).toBeFalsy();
		} );
	} );

	describe( 'getPlatformCheckoutCustomMessage()', () => {
		test( 'returns the value of state.settings.data.platform_checkout_custom_message', () => {
			const state = {
				settings: {
					data: {
						platform_checkout_custom_message: 'test',
					},
				},
			};

			expect( getPlatformCheckoutCustomMessage( state ) ).toEqual(
				'test'
			);
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns [] if missing (tested state: %j)', ( state ) => {
			expect( getPlatformCheckoutCustomMessage( state ) ).toEqual( '' );
		} );
	} );

	describe.each( [
		{ getFunc: getAccountBusinessName, setting: 'account_business_name' },
		{ getFunc: getAccountBusinessURL, setting: 'account_business_url' },
		{
			getFunc: getAccountBusinessSupportAddress,
			setting: 'account_business_support_address',
		},
		{
			getFunc: getAccountBusinessSupportEmail,
			setting: 'account_business_support_email',
		},
		{
			getFunc: getAccountBusinessSupportPhone,
			setting: 'account_business_support_phone',
		},
		{ getFunc: getAccountBrandingLogo, setting: 'account_branding_logo' },
		{ getFunc: getAccountBrandingIcon, setting: 'account_branding_icon' },
		{
			getFunc: getAccountBrandingPrimaryColor,
			setting: 'account_branding_primary_color',
		},
		{
			getFunc: getAccountBrandingSecondaryColor,
			setting: 'account_branding_secondary_color',
		},
	] )( 'Test get method: %j', ( setting ) => {
		test( 'returns the value of state.settings.data.${setting.setting}', () => {
			const state = {
				settings: {
					data: {
						[ setting.setting ]: setting.setting,
					},
				},
			};

			expect( setting.getFunc( state ) ).toEqual( setting.setting );
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( setting.getFunc( state ) ).toEqual( '' );
		} );
	} );
} );
