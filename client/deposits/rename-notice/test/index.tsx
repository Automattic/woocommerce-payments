/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { PayoutsRenameNotice } from '..';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	TourKit: () => <div>Tour Component</div>,
} ) );

declare const global: {
	wcpaySettings: {
		isPayoutsRenameNoticeDismissed: boolean;
	};
};

describe( 'PayoutsRenameNotice', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should render null if isPayoutsRenameNoticeDismissed is true', () => {
		global.wcpaySettings = {
			isPayoutsRenameNoticeDismissed: true,
		};
		const { container } = render( <PayoutsRenameNotice /> );
		expect( container.firstChild ).toBeNull();
	} );

	test( 'should render notice if isPayoutsRenameNoticeDismissed is false', () => {
		global.wcpaySettings = {
			isPayoutsRenameNoticeDismissed: false,
		};
		const { container } = render( <PayoutsRenameNotice /> );
		expect( container ).toMatchSnapshot();
	} );
} );
