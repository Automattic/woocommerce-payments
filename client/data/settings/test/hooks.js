/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	useEnabledPaymentMethodIds,
	useGeneralSettings,
	useSettings,
} from '../hooks';
import { STORE_NAME } from '../../constants';

jest.mock( '@wordpress/data' );

describe( 'Settings hooks tests', () => {
	let actions;
	let selectors;

	beforeEach( () => {
		actions = {};
		selectors = {};

		const selectMock = jest.fn( ( storeName ) => {
			return STORE_NAME === storeName ? selectors : {};
		} );
		useDispatch.mockImplementation( ( storeName ) => {
			return STORE_NAME === storeName ? actions : {};
		} );
		useSelect.mockImplementation( ( cb ) => {
			return cb( selectMock );
		} );
	} );

	describe( 'useEnabledPaymentMethodIds()', () => {
		test( 'returns enabled payment method IDs from selector', () => {
			actions = {
				updateEnabledPaymentMethodIds: jest.fn(),
			};

			selectors = {
				getEnabledPaymentMethodIds: jest.fn( () => [ 'foo', 'bar' ] ),
			};

			const {
				enabledPaymentMethodIds,
				updateEnabledPaymentMethodIds,
			} = useEnabledPaymentMethodIds();
			updateEnabledPaymentMethodIds( [ 'baz', 'quux' ] );

			expect( enabledPaymentMethodIds ).toEqual( [ 'foo', 'bar' ] );
			expect(
				actions.updateEnabledPaymentMethodIds
			).toHaveBeenCalledWith( [ 'baz', 'quux' ] );
		} );
	} );

	describe( 'useGeneralSettings()', () => {
		test( 'returns general settings from selector', () => {
			actions = {
				updateIsWCPayEnabled: jest.fn(),
			};

			selectors = {
				getIsWCPayEnabled: jest.fn( () => 'foo' ),
			};

			const {
				isWCPayEnabled,
				updateIsWCPayEnabled,
			} = useGeneralSettings();
			updateIsWCPayEnabled( 'bar' );

			expect( isWCPayEnabled ).toEqual( 'foo' );
			expect( actions.updateIsWCPayEnabled ).toHaveBeenCalledWith(
				'bar'
			);
		} );
	} );

	describe( 'useSettings()', () => {
		beforeEach( () => {
			actions = {
				saveSettings: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( { foo: 'bar' } ) ),
				hasFinishedResolution: jest.fn(),
				isResolving: jest.fn(),
				isSavingSettings: jest.fn(),
			};
		} );

		test( 'returns settings from selector', () => {
			const { settings, saveSettings } = useSettings();
			saveSettings( 'bar' );

			expect( settings ).toEqual( { foo: 'bar' } );
			expect( actions.saveSettings ).toHaveBeenCalledWith( 'bar' );
		} );

		test( 'returns isLoading = false when isResolving = false and hasFinishedResolution = true', () => {
			selectors.hasFinishedResolution.mockReturnValue( true );
			selectors.isResolving.mockReturnValue( false );

			const { isLoading } = useSettings();

			expect( isLoading ).toBeFalsy();
		} );

		test.each( [
			[ false, false ],
			[ true, false ],
			[ true, true ],
		] )(
			'returns isLoading = true when isResolving = %s and hasFinishedResolution = %s',
			( isResolving, hasFinishedResolution ) => {
				selectors.hasFinishedResolution.mockReturnValue(
					hasFinishedResolution
				);
				selectors.isResolving.mockReturnValue( isResolving );

				const { isLoading } = useSettings();

				expect( isLoading ).toBeTruthy();
			}
		);
	} );
} );
