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
		frtDiscoverBannerDismissed: boolean;
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
			frtDiscoverBannerDismissed: false,
		};
	} );

	it( 'renders', () => {
		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );

	it( 'does not render when dontShowAgain is true', () => {
		global.wcpaySettings = {
			frtDiscoverBannerDismissed: true,
		};

		const { container: frtBanner } = render( <FRTDiscoverabilityBanner /> );

		expect( frtBanner ).toMatchSnapshot();
	} );
} );
