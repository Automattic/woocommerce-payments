/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	useAccountStatementDescriptor,
	useEnabledPaymentMethodIds,
	useIsWCPayEnabled,
	useManualCapture,
	useSettings,
	useTestMode,
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
	usePlatformCheckoutEnabledSettings,
	usePlatformCheckoutCustomMessage,
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

			const [
				enabledPaymentMethodIds,
				updateEnabledPaymentMethodIds,
			] = useEnabledPaymentMethodIds();
			updateEnabledPaymentMethodIds( [ 'baz', 'quux' ] );

			expect( enabledPaymentMethodIds ).toEqual( [ 'foo', 'bar' ] );
			expect(
				actions.updateEnabledPaymentMethodIds
			).toHaveBeenCalledWith( [ 'baz', 'quux' ] );
		} );
	} );

	describe( 'useTestMode()', () => {
		test( 'returns the test mode flag and a function', () => {
			actions = {
				updateIsTestModeEnabled: jest.fn(),
			};

			selectors = {
				getIsTestModeEnabled: jest.fn().mockReturnValue( true ),
			};

			const [ isTestModeEnabled, setTestMode ] = useTestMode();

			expect( isTestModeEnabled ).toEqual( true );
			expect( setTestMode ).toHaveBeenCalledTimes( 0 );

			setTestMode( false );

			expect( actions.updateIsTestModeEnabled ).toHaveBeenCalledWith(
				false
			);
		} );
	} );

	describe( 'useIsWCPayEnabled()', () => {
		test( 'returns the flag value and a function', () => {
			actions = {
				updateIsWCPayEnabled: jest.fn(),
			};

			selectors = {
				getIsWCPayEnabled: jest.fn().mockReturnValue( true ),
			};

			const [ isWCPayEnabled, setWCPayEnabled ] = useIsWCPayEnabled();

			expect( isWCPayEnabled ).toEqual( true );
			expect( setWCPayEnabled ).toHaveBeenCalledTimes( 0 );

			setWCPayEnabled( false );

			expect( actions.updateIsWCPayEnabled ).toHaveBeenCalledWith(
				false
			);
		} );
	} );

	describe( 'useAccountStatementDescriptor()', () => {
		test( 'returns the statement description value and a function', () => {
			actions = {
				updateAccountStatementDescriptor: jest.fn(),
			};

			selectors = {
				getAccountStatementDescriptor: jest
					.fn()
					.mockReturnValue( 'statement value' ),
			};

			const [
				statementDescriptor,
				setStatementDescriptor,
			] = useAccountStatementDescriptor();

			expect( statementDescriptor ).toEqual( 'statement value' );
			expect( setStatementDescriptor ).toHaveBeenCalledTimes( 0 );

			setStatementDescriptor( 'statement value update' );

			expect(
				actions.updateAccountStatementDescriptor
			).toHaveBeenCalledWith( 'statement value update' );
		} );
	} );

	describe( 'useManualCapture()', () => {
		test( 'returns the manual capture flag and a function', () => {
			actions = {
				updateIsManualCaptureEnabled: jest.fn(),
			};

			selectors = {
				getIsManualCaptureEnabled: jest.fn().mockReturnValue( true ),
			};

			const [
				isManualCaptureEnabled,
				setManualCaptureValue,
			] = useManualCapture();

			expect( isManualCaptureEnabled ).toEqual( true );
			expect( setManualCaptureValue ).toHaveBeenCalledTimes( 0 );

			setManualCaptureValue( false );

			expect( actions.updateIsManualCaptureEnabled ).toHaveBeenCalledWith(
				false
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

	describe( 'usePaymentRequestEnabledSettings()', () => {
		test( 'returns payment request settings from selector', () => {
			actions = {
				updateIsPaymentRequestEnabled: jest.fn(),
			};

			selectors = {
				getIsPaymentRequestEnabled: jest.fn( () => true ),
			};

			const [
				isPaymentRequestEnabled,
				updateIsPaymentRequestEnabled,
			] = usePaymentRequestEnabledSettings();

			updateIsPaymentRequestEnabled( false );

			expect( isPaymentRequestEnabled ).toEqual( true );
			expect(
				actions.updateIsPaymentRequestEnabled
			).toHaveBeenCalledWith( false );
		} );
	} );

	describe( 'usePaymentRequestLocations()', () => {
		test( 'returns and updates payment request locations', () => {
			const locationsBeforeUpdate = [];
			const locationsAfterUpdate = [ 'cart' ];

			actions = {
				updatePaymentRequestLocations: jest.fn(),
			};

			selectors = {
				getPaymentRequestLocations: jest.fn(
					() => locationsBeforeUpdate
				),
			};

			const [
				paymentRequestLocations,
				updatePaymentRequestLocations,
			] = usePaymentRequestLocations();

			updatePaymentRequestLocations( locationsAfterUpdate );

			expect( paymentRequestLocations ).toEqual( locationsBeforeUpdate );
			expect(
				actions.updatePaymentRequestLocations
			).toHaveBeenCalledWith( locationsAfterUpdate );
		} );
	} );

	describe( 'usePlatformCheckoutEnabledSettings()', () => {
		test( 'returns platform checkout setting from selector', () => {
			actions = {
				updateIsPlatformCheckoutEnabled: jest.fn(),
			};

			selectors = {
				getIsPlatformCheckoutEnabled: jest.fn( () => true ),
			};

			const [
				isPlatformCheckoutEnabled,
				updateIsPlatformCheckoutEnabled,
			] = usePlatformCheckoutEnabledSettings();

			updateIsPlatformCheckoutEnabled( false );

			expect( isPlatformCheckoutEnabled ).toEqual( true );
			expect(
				actions.updateIsPlatformCheckoutEnabled
			).toHaveBeenCalledWith( false );
		} );
	} );

	describe( 'usePlatformCheckoutCustomMessage()', () => {
		test( 'returns and updates platform checkout custom message', () => {
			const messageBeforeUpdate = '';
			const messageAfterUpdate = 'test';

			actions = {
				updatePlatformCheckoutCustomMessage: jest.fn(),
			};

			selectors = {
				getPlatformCheckoutCustomMessage: jest.fn(
					() => messageBeforeUpdate
				),
			};

			const [
				platformCheckoutCustomMessage,
				updatePlatformCheckoutCustomMessage,
			] = usePlatformCheckoutCustomMessage();

			updatePlatformCheckoutCustomMessage( messageAfterUpdate );

			expect( platformCheckoutCustomMessage ).toEqual(
				messageBeforeUpdate
			);
			expect(
				actions.updatePlatformCheckoutCustomMessage
			).toHaveBeenCalledWith( messageAfterUpdate );
		} );
	} );
} );
