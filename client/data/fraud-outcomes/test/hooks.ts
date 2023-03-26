/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useLatestFraudOutcome } from '../';
import { STORE_NAME } from '../../constants';
import { FraudOutcome } from '../../../types/fraud-outcome';

jest.mock( '@wordpress/data' );

export const paymentIntentId = 'pi_mock';

export const latestFraudOutcomeMock: FraudOutcome = {
	status: 'review',
};

declare const global: {
	wcpaySettings: {
		isFraudProtectionSettingsEnabled: boolean;
	};
};

describe( 'Fraud outcomes hooks', () => {
	let selectors: Record< string, () => any >;

	beforeEach( () => {
		selectors = {};
		global.wcpaySettings = {
			isFraudProtectionSettingsEnabled: false,
		};

		const selectMock = jest.fn( ( storeName ) =>
			STORE_NAME === storeName ? selectors : {}
		);

		( useSelect as jest.Mock ).mockImplementation(
			( cb: ( callback: any ) => jest.Mock ) => cb( selectMock )
		);
	} );

	describe( 'useLatestFraudOutcome', () => {
		it( 'should return the correct data with the feature flag enabled', async () => {
			global.wcpaySettings.isFraudProtectionSettingsEnabled = true;

			selectors = {
				getLatestFraudOutcome: jest
					.fn()
					.mockReturnValue( latestFraudOutcomeMock ),
				getLatestFraudOutcomeError: jest
					.fn()
					.mockReturnValue( undefined ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = useLatestFraudOutcome( paymentIntentId );

			expect( result ).toEqual( {
				data: latestFraudOutcomeMock,
				error: undefined,
				isLoading: false,
			} );
		} );

		it( 'should return the correct data with the feature flag disabled', async () => {
			selectors = {
				getLatestFraudOutcome: jest.fn().mockReturnValue( {} ),
				getLatestFraudOutcomeError: jest
					.fn()
					.mockReturnValue( undefined ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = useLatestFraudOutcome( paymentIntentId );

			expect( result ).toEqual( {
				data: undefined,
				error: undefined,
				isLoading: false,
			} );
		} );
	} );
} );
