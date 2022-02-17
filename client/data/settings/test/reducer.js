/**
 * Internal dependencies
 */
import reducer from '../reducer';
import {
	updateSettings,
	updateIsSavingSettings,
	updateIsManualCaptureEnabled,
	updateAccountStatementDescriptor,
	updatePaymentRequestLocations,
	updateIsPaymentRequestEnabled,
	updateAccountBusinessName,
	updateAccountBusinessURL,
	updateAccountBusinessSupportAddress,
	updateAccountBusinessSupportEmail,
	updateAccountBusinessSupportPhone,
	updateAccountBrandingLogo,
	updateAccountBrandingIcon,
	updateAccountBrandingPrimaryColor,
	updateAccountBrandingSecondaryColor,
	updateIsPlatformCheckoutEnabled,
	updatePlatformCheckoutCustomMessage,
} from '../actions';

describe( 'Settings reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( undefined, { type: 'foo' } );

		expect( defaultState ).toEqual( {
			isSaving: false,
			data: {},
			savingError: null,
		} );
	} );

	describe( 'SET_SETTINGS', () => {
		test( 'sets the `data` field', () => {
			const settings = {
				foo: 'bar',
			};

			const state = reducer( undefined, updateSettings( settings ) );

			expect( state.data ).toEqual( settings );
		} );

		test( 'overwrites existing settings in the `data` field', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const newSettings = {
				baz: 'quux',
			};

			const state = reducer( oldState, updateSettings( newSettings ) );

			expect( state.data ).toEqual( newSettings );
		} );

		test( 'leaves fields other than `data` unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					baz: 'quux',
				},
				savingError: {},
			};

			const newSettings = {
				quuz: 'corge',
			};

			const state = reducer( oldState, updateSettings( newSettings ) );

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					quuz: 'corge',
				},
				savingError: {},
			} );
		} );
	} );

	describe( 'SET_IS_SAVING', () => {
		test( 'toggles isSaving', () => {
			const oldState = {
				isSaving: false,
				savingError: null,
			};

			const state = reducer(
				oldState,
				updateIsSavingSettings( true, {} )
			);

			expect( state.isSaving ).toBeTruthy();
			expect( state.savingError ).toEqual( {} );
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				isSaving: false,
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateIsSavingSettings( true, null )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				isSaving: true,
			} );
		} );
	} );

	describe( 'SET_IS_MANUAL_CAPTURE_ENABLED', () => {
		test( 'toggles `data.is_manual_capture_enabled`', () => {
			const oldState = {
				data: {
					is_manual_capture_enabled: false,
				},
			};

			const state = reducer(
				oldState,
				updateIsManualCaptureEnabled( true )
			);

			expect( state.data.is_manual_capture_enabled ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					is_manual_capture_enabled: false,
					baz: 'quux',
				},
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateIsManualCaptureEnabled( true )
			);

			expect( state ).toEqual( {
				savingError: null,
				foo: 'bar',
				data: {
					is_manual_capture_enabled: true,
					baz: 'quux',
				},
			} );
		} );
	} );

	describe( 'SET_ACCOUNT_STATEMENT_DESCRIPTOR', () => {
		test( 'toggles `data.account_statement_descriptor`', () => {
			const oldState = {
				data: {
					account_statement_descriptor: 'Statement',
				},
			};

			const state = reducer(
				oldState,
				updateAccountStatementDescriptor( 'New Statement' )
			);

			expect( state.data.account_statement_descriptor ).toEqual(
				'New Statement'
			);
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					account_statement_descriptor: 'Statement',
					baz: 'quux',
				},
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateAccountStatementDescriptor( 'New Statement' )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				data: {
					account_statement_descriptor: 'New Statement',
					baz: 'quux',
				},
			} );
		} );
	} );

	describe( 'SET_IS_PAYMENT_REQUEST_ENABLED', () => {
		test( 'toggles `data.is_payment_request_enabled`', () => {
			const oldState = {
				data: {
					is_payment_request_enabled: false,
				},
				savingError: null,
			};

			const state = reducer(
				oldState,
				updateIsPaymentRequestEnabled( true )
			);

			expect( state.data.is_payment_request_enabled ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					is_payment_request_enabled: false,
					baz: 'quux',
				},
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateIsPaymentRequestEnabled( true )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				data: {
					is_payment_request_enabled: true,
					baz: 'quux',
				},
			} );
		} );
	} );

	describe( 'SET_PAYMENT_REQUEST_LOCATIONS', () => {
		const initPaymentRequestState = [ 'product' ];
		const enableAllpaymentRequestState = [ 'product', 'checkout', 'cart' ];

		test( 'toggle `data.payment_request_enabled_locations`', () => {
			const oldState = {
				data: {
					payment_request_enabled_locations: initPaymentRequestState,
				},
			};

			const state = reducer(
				oldState,
				updatePaymentRequestLocations( enableAllpaymentRequestState )
			);

			expect( state.data.payment_request_enabled_locations ).toEqual(
				enableAllpaymentRequestState
			);
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					payment_request_enabled_locations: initPaymentRequestState,
					baz: 'quux',
				},
				savingError: {},
			};

			const state = reducer(
				oldState,
				updatePaymentRequestLocations( enableAllpaymentRequestState )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					payment_request_enabled_locations: enableAllpaymentRequestState,
					baz: 'quux',
				},
				savingError: null,
			} );
		} );
	} );

	describe( 'SET_MERCHANT_SETTINGS', () => {
		const merchantSettings = [
			{
				updateFunc: updateAccountBusinessName,
				stateKey: 'account_business_name',
				settingValue: 'Business name',
			},
			{
				updateFunc: updateAccountBusinessURL,
				stateKey: 'account_business_url',
				settingValue: 'Business url',
			},
			{
				updateFunc: updateAccountBusinessSupportAddress,
				stateKey: 'account_business_support_address',
				settingValue: 'Business address',
			},
			{
				updateFunc: updateAccountBusinessSupportEmail,
				stateKey: 'account_business_support_email',
				settingValue: 'Business email',
			},
			{
				updateFunc: updateAccountBusinessSupportPhone,
				stateKey: 'account_business_support_phone',
				settingValue: 'Business phone',
			},
			{
				updateFunc: updateAccountBrandingLogo,
				stateKey: 'account_branding_logo',
				settingValue: 'Branding logo',
			},
			{
				updateFunc: updateAccountBrandingIcon,
				stateKey: 'account_branding_icon',
				settingValue: 'Branding icon',
			},
			{
				updateFunc: updateAccountBrandingPrimaryColor,
				stateKey: 'account_branding_primary_color',
				settingValue: 'Branding primary color',
			},
			{
				updateFunc: updateAccountBrandingSecondaryColor,
				stateKey: 'account_branding_secondary_color',
				settingValue: 'Branding secondary color',
			},
		];

		test.each( merchantSettings )( 'toggles `%j`', ( setting ) => {
			const oldState = {
				data: {
					[ setting.stateKey ]: setting.settingValue,
				},
			};

			const state = reducer(
				oldState,
				setting.updateFunc( 'New ${setting.settingValue}' )
			);

			expect( state.data[ setting.stateKey ] ).toEqual(
				'New ${setting.settingValue}'
			);
		} );

		test.each( merchantSettings )(
			'leaves other fields unchanged `%j`',
			( setting ) => {
				const oldState = {
					foo: 'bar',
					data: {
						[ setting.stateKey ]: setting.settingValue,
						baz: 'quux',
					},
					savingError: {},
				};

				const state = reducer(
					oldState,
					setting.updateFunc( 'New ${setting.settingValue}' )
				);

				expect( state ).toEqual( {
					foo: 'bar',
					savingError: null,
					data: {
						[ setting.stateKey ]: 'New ${setting.settingValue}',
						baz: 'quux',
					},
				} );
			}
		);
	} );

	describe( 'SET_IS_PLATFORM_CHECKOUT_ENABLED', () => {
		test( 'toggles `data.is_platform_checkout_enabled`', () => {
			const oldState = {
				data: {
					is_platform_checkout_enabled: false,
				},
				savingError: null,
			};

			const state = reducer(
				oldState,
				updateIsPlatformCheckoutEnabled( true )
			);

			expect( state.data.is_platform_checkout_enabled ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					is_platform_checkout_enabled: false,
					baz: 'quux',
				},
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateIsPlatformCheckoutEnabled( true )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				data: {
					is_platform_checkout_enabled: true,
					baz: 'quux',
				},
			} );
		} );
	} );

	describe( 'SET_PLATFORM_CHECKOUT_CUSTOM_MESSAGE', () => {
		test( 'toggle `data.platform_checkout_custom_message`', () => {
			const oldState = {
				data: {
					platform_checkout_custom_message: '',
				},
			};

			const state = reducer(
				oldState,
				updatePlatformCheckoutCustomMessage( 'test' )
			);

			expect( state.data.platform_checkout_custom_message ).toEqual(
				'test'
			);
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					platform_checkout_custom_message: '',
					baz: 'quux',
				},
				savingError: {},
			};

			const state = reducer(
				oldState,
				updatePlatformCheckoutCustomMessage( 'test' )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					platform_checkout_custom_message: 'test',
					baz: 'quux',
				},
				savingError: null,
			} );
		} );
	} );
} );
