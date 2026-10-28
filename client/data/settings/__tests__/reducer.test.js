/**
 * Internal dependencies
 */
import reducer from '../reducer';
import {
	updateSettings,
	updateIsSavingSettings,
	updateIsManualCaptureEnabled,
	updateAccountStatementDescriptor,
	updateIsPaymentRequestEnabled,
	updateAccountBusinessSupportEmail,
	updateAccountBusinessSupportPhone,
	updateIsWooPayEnabled,
	updateWooPayCustomMessage,
	updateWooPayStoreLogo,
} from '../actions';

describe( 'Settings reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( undefined, { type: 'foo' } );

		expect( defaultState ).toEqual( {
			isSaving: false,
			data: {},
			savingError: null,
			isDirty: false,
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
				isDirty: false,
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
				isDirty: false,
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
				isDirty: false,
				foo: 'bar',
				isSaving: false,
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateIsSavingSettings( true, null )
			);

			expect( state ).toEqual( {
				isDirty: false,
				foo: 'bar',
				savingError: null,
				isSaving: true,
			} );
		} );
	} );

	describe( 'SET_SETTINGS_VALUES', () => {
		test( 'merges the value into `data`, marks the state dirty and clears the saving error', () => {
			const oldState = {
				isDirty: false,
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
				isDirty: true,
				savingError: null,
				foo: 'bar',
				data: {
					is_manual_capture_enabled: true,
					baz: 'quux',
				},
			} );
		} );

		test.each( [
			[
				updateAccountStatementDescriptor,
				'account_statement_descriptor',
			],
			[ updateIsPaymentRequestEnabled, 'is_payment_request_enabled' ],
			[
				updateAccountBusinessSupportEmail,
				'account_business_support_email',
			],
			[
				updateAccountBusinessSupportPhone,
				'account_business_support_phone',
			],
			[ updateIsWooPayEnabled, 'is_woopay_enabled' ],
			[ updateWooPayCustomMessage, 'woopay_custom_message' ],
			[ updateWooPayStoreLogo, 'woopay_store_logo' ],
		] )( '%p updates `data.%s`', ( updateFunc, stateKey ) => {
			const NEW_VALUE = 'new value';

			const state = reducer(
				{ data: { [ stateKey ]: 'old value' } },
				updateFunc( NEW_VALUE )
			);

			expect( state.data[ stateKey ] ).toEqual( 'new value' );
		} );
	} );
} );
