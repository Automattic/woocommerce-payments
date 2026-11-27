/**
 * External dependencies
 */
import React from 'react';
import { cleanup, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { getQuery } from '@woocommerce/navigation';
import SettingsManager from '..';
import { useSettings } from 'wcpay/data/settings';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn(),
	updateQueryString: jest.fn(),
} ) );

jest.mock( 'wcpay/data/settings', () => ( {
	useSettings: jest.fn(),
	useDepositDelayDays: jest.fn( () => ( {} ) ),
	useGetDuplicatedPaymentMethodIds: jest.fn( () => [] ),
} ) );

jest.mock( '../../settings-layout', () => ( { children } ) => (
	<div>{ children }</div>
) );
jest.mock( '../../loadable-settings-section', () => ( { children } ) => (
	<div>{ children }</div>
) );
jest.mock( '../../settings-section', () => ( { children } ) => (
	<div>{ children }</div>
) );
jest.mock( '../../payment-methods-section', () => () => (
	<div>Payments accepted on checkout</div>
) );
jest.mock( '../../express-checkout', () => () => null );
jest.mock( '../../buy-now-pay-later-section', () => () => null );
jest.mock( '../../general-settings', () => () => null );
jest.mock( '../../transactions', () => () => null );
jest.mock( '../../deposits', () => () => null );
jest.mock( '../../advanced-settings', () => () => null );
jest.mock( '../../fraud-protection', () => () => null );
jest.mock( '../../save-settings-section', () => () => null );
jest.mock( '../../notification-settings', () => () => null );
jest.mock( '../../../vat/form-modal', () => () => null );
jest.mock( 'promotions/spotlight', () => () => null );

describe( 'SettingsManager scrolling to the anchor', () => {
	const TARGET_ID = 'scroll-target';
	let scrollTo;

	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {},
			dismissedDuplicateNotices: {},
		};
		useSettings.mockReturnValue( {
			isLoading: false,
			isDirty: false,
			isSaving: false,
		} );
		scrollTo = jest.spyOn( window, 'scrollTo' ).mockImplementation();

		const target = document.createElement( 'div' );
		target.id = TARGET_ID;
		document.body.appendChild( target );
	} );

	afterEach( () => {
		cleanup();
		scrollTo.mockRestore();
		document.getElementById( TARGET_ID )?.remove();
	} );

	it( 'scrolls to the element in the anchor query arg', async () => {
		getQuery.mockReturnValue( { anchor: '#scroll-target' } );

		render( <SettingsManager /> );

		await waitFor( () => expect( scrollTo ).toHaveBeenCalledTimes( 1 ) );
	} );

	it( 'does not scroll when the anchor matches no element', async () => {
		getQuery.mockReturnValue( { anchor: '#payment-method-nope' } );

		render( <SettingsManager /> );

		await screen.findByText( 'Payments accepted on checkout' );
		expect( scrollTo ).not.toHaveBeenCalled();
	} );

	it( 'still renders when the anchor is not a valid selector', async () => {
		getQuery.mockReturnValue( { anchor: '[' } );

		render( <SettingsManager /> );

		expect(
			await screen.findByText( 'Payments accepted on checkout' )
		).toBeInTheDocument();
		expect( scrollTo ).not.toHaveBeenCalled();
	} );
} );
