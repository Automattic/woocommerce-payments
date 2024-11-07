/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { log, error } from 'console';

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
