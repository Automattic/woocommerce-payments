/**
 * External dependencies
 */
import { renderHook } from '@testing-library/react-hooks';
import { useUserPreferences } from '@woocommerce/data';

/**
 * Internal dependencies
 */
import { useWcpayUserPreferences } from '../use-wcpay-user-preferences';

jest.mock( '@woocommerce/data', () => ( {
	useUserPreferences: jest.fn(),
} ) );

const mockUseUserPreferences = useUserPreferences as jest.MockedFunction<
	typeof useUserPreferences
>;

const updateUserPreferences = jest.fn();

declare const global: {
	wcpaySettings: {
		userPreferences?: Record< string, string >;
	};
};

/**
 * Stand in for what WooCommerce Admin hands us, which carries only the preferences it
 * managed to read.
 *
 * @param preferences Decoded preferences WooCommerce Admin resolved.
 */
const mockLivePreferences = ( preferences: Record< string, unknown > ) => {
	mockUseUserPreferences.mockReturnValue( {
		isRequesting: false,
		updateUserPreferences,
		...preferences,
	} as unknown as ReturnType< typeof useUserPreferences > );
};

/**
 * Stand in for the raw values we render into the page ourselves.
 *
 * @param preferences Raw stored values.
 */
const mockRenderedPreferences = ( preferences: Record< string, string > ) => {
	global.wcpaySettings = {
		...global.wcpaySettings,
		userPreferences: preferences,
	};
};

/**
 * Render the hook and expose the merged bag, whose names are only known at runtime.
 */
const renderPreferences = () => {
	const { result } = renderHook( () => useWcpayUserPreferences() );

	return result.current as unknown as Record< string, unknown > &
		ReturnType< typeof useUserPreferences >;
};

describe( 'useWcpayUserPreferences', () => {
	const originalSettings = global.wcpaySettings;

	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = { ...originalSettings };
	} );

	afterAll( () => {
		global.wcpaySettings = originalSettings;
	} );

	it( 'falls back to the rendered values when WooCommerce Admin resolved none', () => {
		mockLivePreferences( {} );
		mockRenderedPreferences( {
			wc_payments_payouts_hidden_columns: '["date","status"]',
		} );

		expect(
			renderPreferences().wc_payments_payouts_hidden_columns
		).toEqual( [ 'date', 'status' ] );
	} );

	it( 'prefers the value WooCommerce Admin resolved over the rendered one', () => {
		mockLivePreferences( {
			wc_payments_payouts_hidden_columns: [ 'amount' ],
		} );
		mockRenderedPreferences( {
			wc_payments_payouts_hidden_columns: '["date","status"]',
		} );

		expect(
			renderPreferences().wc_payments_payouts_hidden_columns
		).toEqual( [ 'amount' ] );
	} );

	it( 'keeps a cleared value rather than falling back to the rendered one', () => {
		mockLivePreferences( { wc_payments_payouts_hidden_columns: '' } );
		mockRenderedPreferences( {
			wc_payments_payouts_hidden_columns: '["date","status"]',
		} );

		expect( renderPreferences().wc_payments_payouts_hidden_columns ).toBe(
			''
		);
	} );

	it( 'decodes rendered values the way WooCommerce Admin does', () => {
		mockLivePreferences( {} );
		mockRenderedPreferences( {
			json_object: '{"perPage":25}',
			json_number: '1750000000',
			plain_string: 'yes',
			unset: '',
		} );

		expect( renderPreferences() ).toEqual(
			expect.objectContaining( {
				json_object: { perPage: 25 },
				json_number: 1750000000,
				plain_string: 'yes',
				unset: '',
			} )
		);
	} );

	it( 'passes through everything else the WooCommerce Admin hook returns', () => {
		mockLivePreferences( {} );
		mockRenderedPreferences( {} );

		const preferences = renderPreferences();

		expect( preferences.isRequesting ).toBe( false );
		expect( preferences.updateUserPreferences ).toBe(
			updateUserPreferences
		);
	} );

	it( 'copes with no rendered values at all', () => {
		mockLivePreferences( {
			wc_payments_payouts_hidden_columns: [ 'fee' ],
		} );
		delete global.wcpaySettings.userPreferences;

		expect(
			renderPreferences().wc_payments_payouts_hidden_columns
		).toEqual( [ 'fee' ] );
	} );
} );
