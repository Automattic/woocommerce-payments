/**
 * Internal dependencies
 */
import {
	getSettings,
	getIsWCPayEnabled,
	getEnabledPaymentMethodIds,
	isSavingSettings,
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
						// eslint-disable-next-line camelcase
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
						// eslint-disable-next-line camelcase
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
} );
