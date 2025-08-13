/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { saveSettings, updateIsSavingSettings } from '../actions';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

describe( 'Settings actions tests', () => {
	describe( 'saveSettings()', () => {
		beforeEach( () => {
			const noticesDispatch = {
				createSuccessNotice: jest.fn(),
				createErrorNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return {};
			} );
			select.mockImplementation( () => ( {
				getSettings: jest.fn(),
			} ) );
		} );

		test( 'makes POST request with settings', () => {
			const settingsMock = {
				enabled_payment_method_ids: [ 'foo', 'bar' ],
				is_wcpay_enabled: true,
			};

			select.mockReturnValue( {
				getSettings: () => settingsMock,
			} );

			apiFetch.mockReturnValue( 'api response' );

			const yielded = [ ...saveSettings() ];

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'post',
				path: '/wc/v3/payments/settings',
				data: settingsMock,
			} );
			expect( yielded ).toContainEqual( 'api response' );
		} );

		test( 'before saving sets isSaving to true, and after - to false', () => {
			const apiResponse = {
				data: {
					payment_method_statuses: {
						bancontact: 'active',
					},
				},
			};
			apiFetch.mockReturnValue( { ...apiResponse } );

			const saveGenerator = saveSettings();

			// Assert the first yield is updating isSaving to true
			let next = saveGenerator.next();
			expect( next.value ).toEqual(
				updateIsSavingSettings( true, null )
			);

			// Execute the next step, which should be the apiFetch call
			next = saveGenerator.next();
			expect( next.value ).toEqual( apiResponse );

			// Simulate the response from the apiFetch call and proceed to the next yield
			// Since the actual fetching process is mocked, pass the apiResponse to the next saveGenerator step directly
			next = saveGenerator.next( apiResponse );
			expect( next.value ).toEqual( {
				type: 'SET_SETTINGS_VALUES',
				payload: {
					payment_method_statuses:
						apiResponse.data.payment_method_statuses,
				},
			} );

			next = saveGenerator.next(); // Skip the success notice
			next = saveGenerator.next(); // Move to updateIsSavingSettings(false)
			expect( next.value ).toEqual(
				updateIsSavingSettings( false, null )
			);

			// Check if the saveGenerator is complete
			expect( saveGenerator.next().done ).toBeTruthy();
		} );

		test( 'displays success notice after saving', () => {
			const apiResponse = {
				data: {
					payment_method_statuses: {
						bancontact: 'active',
					},
				},
			};
			apiFetch.mockReturnValue( { ...apiResponse } );

			// Execute the generator until the end
			const saveGenerator = saveSettings();
			while ( ! saveGenerator.next( apiResponse ).done ) {
				// Intentionally empty
			}
			expect( saveGenerator.next().done ).toBeTruthy();

			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).toHaveBeenCalledWith( 'Settings saved.' );
		} );

		test( 'displays error notice if error is thrown', () => {
			const saveGenerator = saveSettings();

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			// eslint-disable-next-line no-unused-expressions
			[ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalledWith( 'Error saving settings.' );
			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).not.toHaveBeenCalled();
		} );

		test( 'after throwing error, isSaving is reset', () => {
			const saveGenerator = saveSettings();

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			const yielded = [ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalled();
			expect( yielded ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						type: 'SET_IS_SAVING_SETTINGS',
						isSaving: false,
					} ),
				] )
			);
		} );
	} );
} );
