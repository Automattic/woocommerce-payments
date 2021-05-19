/**
 * Internal dependencies
 */
import {
	getSettings,
	getIsWCPayEnabled,
	getEnabledPaymentMethodIds,
	isSavingSettings,
	getIsDigitalWalletsEnabled,
	getDigitalWalletsSections,
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

	describe( 'getIsDigitalWalletsEnabled()', () => {
		test( 'returns the value of state.settings.data.is_digital_wallets_enabled', () => {
			const state = {
				settings: {
					data: {
						// eslint-disable-next-line camelcase
						is_digital_wallets_enabled: true,
					},
				},
			};

			expect( getIsDigitalWalletsEnabled( state ) ).toBeTruthy();
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getIsDigitalWalletsEnabled( state ) ).toBeFalsy();
		} );
	} );

	describe( 'getDigitalWalletsSections()', () => {
		test( 'returns the value of state.settings.data.digital_wallets_enabled_sections', () => {
			const state = {
				settings: {
					data: {
						// eslint-disable-next-line camelcase
						digital_wallets_enabled_sections: {
							// eslint-disable-next-line camelcase
							product_page: true,
							checkout: false,
							cart: true,
						},
					},
				},
			};

			expect( getDigitalWalletsSections( state ) ).toEqual( {
				// eslint-disable-next-line camelcase
				product_page: true,
				checkout: false,
				cart: true,
			} );
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns defaults if missing (tested state: %j)', ( state ) => {
			expect( getDigitalWalletsSections( state ) ).toEqual( {
				checkout: true,
				// eslint-disable-next-line camelcase
				product_page: true,
				cart: true,
			} );
		} );
	} );
} );
