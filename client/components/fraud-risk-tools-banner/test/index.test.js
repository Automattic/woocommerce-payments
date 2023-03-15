/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FRTDiscoverabilityBanner from '..';

jest.mock( '@wordpress/data', () => ( {
	registerStore: jest.fn(),
	combineReducers: jest.fn(),
	useDispatch: jest.fn( () => ( { updateOptions: jest.fn() } ) ),
	dispatch: jest.fn( () => ( { setIsMatching: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	createRegistryControl: jest.fn(),
	select: jest.fn(),
	withSelect: jest.fn( () => jest.fn() ),
	useSelect: jest.fn( () => ( { getNotices: jest.fn() } ) ),
} ) );
jest.mock( '@wordpress/data-controls' );
jest.mock( 'wcpay/data', () => ( {
	useSettings: jest.fn().mockReturnValue( {
		settings: { enabled_payment_method_ids: [ 'foo', 'bar' ] },
	} ),
	useAllDepositsOverviews: jest
		.fn()
		.mockReturnValue( { overviews: { currencies: [] } } ),
	useActiveLoanSummary: jest.fn().mockReturnValue( { isLoading: true } ),
} ) );

describe( 'FRTDiscoverabilityBanner', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			isFraudProtectionSettingsEnabled: true,
		};
	} );

	it( 'renders', () => {
		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'does not render if feature flag option is false', () => {
		global.wcpaySettings = {
			isFraudProtectionSettingsEnabled: false,
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'renders with dont show again button if remindMeCount greater than or equal to 3', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			frtDiscoverBannerSettings: JSON.stringify( {
				remindMeCount: 3,
				remindMeAt: null,
				dontShowAgain: false,
			} ),
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'renders without dont show again button if remindMeCount greater than 0 but less than 3', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			frtDiscoverBannerSettings: JSON.stringify( {
				remindMeCount: 2,
				remindMeAt: null,
				dontShowAgain: false,
			} ),
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'renders when remindMeAt timestamp is in the past', () => {
		Date.now = jest.fn( () =>
			new Date( '2023-03-14T12:33:37.000Z' ).getTime()
		);

		const remindMeAt = new Date( '2023-03-11T11:33:37.000Z' ).getTime();

		global.wcpaySettings = {
			...global.wcpaySettings,
			frtDiscoverBannerSettings: JSON.stringify( {
				remindMeCount: 1,
				remindMeAt: remindMeAt,
				dontShowAgain: false,
			} ),
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'does not render when remindMeAt timestamp is in the future', () => {
		Date.now = jest.fn( () =>
			new Date( '2023-03-14T12:33:37.000Z' ).getTime()
		);

		const remindMeAt = new Date( '2023-03-15T12:33:37.000Z' ).getTime();

		global.wcpaySettings = {
			...global.wcpaySettings,
			frtDiscoverBannerSettings: JSON.stringify( {
				remindMeCount: 1,
				remindMeAt: remindMeAt,
				dontShowAgain: false,
			} ),
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'does not render when dontShowAgain is true', () => {
		const remindMeAt = new Date( '2023-03-14T12:33:37.000Z' ).getTime();

		global.wcpaySettings = {
			...global.wcpaySettings,
			frtDiscoverBannerSettings: JSON.stringify( {
				remindMeCount: 3,
				remindMeAt: remindMeAt,
				dontShowAgain: true,
			} ),
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );
} );
