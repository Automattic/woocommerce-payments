/**
 * Internal dependencies
 */
import reducer from '../reducer';
import {
	updateSettings,
	updateIsWCPayEnabled,
	updateEnabledPaymentMethodIds,
	updateIsSavingSettings,
	updateIsDigitalWalletsEnabled,
	updateDigitalWalletsLocations,
} from '../actions';

describe( 'Settings reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( undefined, { type: 'foo' } );

		expect( defaultState ).toEqual( {
			isSaving: false,
			data: {},
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
			} );
		} );
	} );

	describe( 'SET_IS_SAVING', () => {
		test( 'toggles isSaving', () => {
			const oldState = {
				isSaving: false,
			};

			const state = reducer( oldState, updateIsSavingSettings( true ) );

			expect( state.isSaving ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				isSaving: false,
			};

			const state = reducer( oldState, updateIsSavingSettings( true ) );

			expect( state ).toEqual( {
				foo: 'bar',
				isSaving: true,
			} );
		} );
	} );

	describe( 'SET_ENABLED_PAYMENT_METHOD_IDS', () => {
		test( 'sets `data.enabled_payment_method_ids`', () => {
			const oldState = {
				data: {
					enabled_payment_method_ids: [], // eslint-disable-line
				},
			};

			const methodIds = [ 'foo', 'bar' ];

			const state = reducer(
				oldState,
				updateEnabledPaymentMethodIds( methodIds )
			);

			expect( state.data.enabled_payment_method_ids ).toEqual(
				methodIds
			);
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				baz: 'quux',
				data: {
					enabled_payment_method_ids: [], // eslint-disable-line
					quuz: 'corge',
				},
			};

			const methodIds = [ 'foo', 'bar' ];

			const state = reducer(
				oldState,
				updateEnabledPaymentMethodIds( methodIds )
			);

			expect( state ).toEqual( {
				baz: 'quux',
				data: {
					enabled_payment_method_ids: methodIds, // eslint-disable-line
					quuz: 'corge',
				},
			} );
		} );
	} );

	describe( 'SET_IS_WCPAY_ENABLED', () => {
		test( 'toggles `data.is_wcpay_enabled`', () => {
			const oldState = {
				data: {
					is_wcpay_enabled: false, // eslint-disable-line
				},
			};

			const state = reducer( oldState, updateIsWCPayEnabled( true ) );

			expect( state.data.is_wcpay_enabled ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					is_wcpay_enabled: false, // eslint-disable-line
					baz: 'quux',
				},
			};

			const state = reducer( oldState, updateIsWCPayEnabled( true ) );

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					is_wcpay_enabled: true, // eslint-disable-line
					baz: 'quux',
				},
			} );
		} );
	} );

	describe( 'SET_IS_DIGITAL_WALLETS_ENABLED', () => {
		test( 'toggles `data.is_digital_wallets_enabled`', () => {
			const oldState = {
				data: {
					// eslint-disable-next-line camelcase
					is_digital_wallets_enabled: false,
				},
			};

			const state = reducer(
				oldState,
				updateIsDigitalWalletsEnabled( true )
			);

			expect( state.data.is_digital_wallets_enabled ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					// eslint-disable-next-line camelcase
					is_digital_wallets_enabled: false,
					baz: 'quux',
				},
			};

			const state = reducer(
				oldState,
				updateIsDigitalWalletsEnabled( true )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					// eslint-disable-next-line camelcase
					is_digital_wallets_enabled: true,
					baz: 'quux',
				},
			} );
		} );
	} );

	describe( 'SET_DIGITAL_WALLETS_LOCATIONS', () => {
		const initDigitalWalletsState = {
			// eslint-disable-next-line camelcase
			product_page: true,
			checkout: false,
			cart: false,
		};
		const enableAlldigitalWalletsState = {
			// eslint-disable-next-line camelcase
			product_page: true,
			checkout: true,
			cart: true,
		};

		test( 'toggle `data.digital_wallets_enabled_locations`', () => {
			const oldState = {
				data: {
					// eslint-disable-next-line camelcase
					digital_wallets_enabled_locations: initDigitalWalletsState,
				},
			};

			const state = reducer(
				oldState,
				updateDigitalWalletsLocations( enableAlldigitalWalletsState )
			);

			expect( state.data.digital_wallets_enabled_locations ).toEqual(
				enableAlldigitalWalletsState
			);
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					// eslint-disable-next-line camelcase
					digital_wallets_enabled_locations: initDigitalWalletsState,
					baz: 'quux',
				},
			};

			const state = reducer(
				oldState,
				updateDigitalWalletsLocations( enableAlldigitalWalletsState )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					// eslint-disable-next-line camelcase
					digital_wallets_enabled_locations: enableAlldigitalWalletsState,
					baz: 'quux',
				},
			} );
		} );
	} );
} );
