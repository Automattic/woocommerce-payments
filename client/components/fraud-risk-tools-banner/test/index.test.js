/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FRTDiscoverabilityBanner from '..';

jest.mock( '@woocommerce/components', () => {
	return {
		Pill: ( { className, children } ) => (
			<span className={ className }>{ children }</span>
		),
	};
} );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { updateOptions: jest.fn() } ) ),
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
