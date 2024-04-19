/**
 * External dependencies
 */
import React, { HTMLAttributes } from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FRTDiscoverabilityBanner from '..';

declare const global: {
	wcpaySettings: {
		frtDiscoverBannerSettings: string;
		lifetimeTPV: number;
	};
};

jest.mock( '@woocommerce/components', () => {
	const Pill: React.FC< HTMLAttributes< HTMLDivElement > > = ( {
		className,
		children,
	} ) => <span className={ className }>{ children }</span>;

	return { Pill };
} );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { updateOptions: jest.fn() } ) ),
} ) );

describe( 'FRTDiscoverabilityBanner', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			frtDiscoverBannerSettings: '',
			lifetimeTPV: 100,
		};
	} );

	it( 'renders', () => {
		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'does not render when dontShowAgain is true', () => {
		global.wcpaySettings = {
			frtDiscoverBannerSettings: JSON.stringify( {
				dontShowAgain: true,
			} ),
			lifetimeTPV: 100,
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'does not render when no transactions are processed', () => {
		global.wcpaySettings = {
			frtDiscoverBannerSettings: JSON.stringify( {
				dontShowAgain: false,
			} ),
			lifetimeTPV: 0,
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );
} );
