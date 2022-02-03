/**
 * External dependencies
 */
import { render, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import {
	useAccountBusinessName,
	useAccountBusinessSupportAddress,
	useAccountBusinessSupportEmail,
	useAccountBusinessSupportPhone,
	useAccountBusinessURL,
} from 'wcpay/data';
import PreviewPrintReceipt from '..';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( 'wcpay/data', () => ( {
	useAccountBusinessSupportAddress: jest.fn(),
	useAccountBusinessName: jest.fn(),
	useAccountBusinessURL: jest.fn(),
	useAccountBusinessSupportEmail: jest.fn(),
	useAccountBusinessSupportPhone: jest.fn(),
} ) );

const mockSettings = {
	accountBusinessSupportAddress: {
		line1: 'line1',
		line2: 'line2',
		city: 'city',
		postal_code: '42',
		country: 'US',
	},
	accountBusinessName: 'Test',
	accountBusinessURL: 'https:\\test',
	accountBusinessSupportEmail: 'test@example.com',
	accountBusinessSupportPhone: '42424242',
};

const mockAccountSettings = ( {
	accountBusinessSupportAddress,
	accountBusinessName,
	accountBusinessURL,
	accountBusinessSupportEmail,
	accountBusinessSupportPhone,
} ) => {
	useAccountBusinessSupportAddress.mockReturnValue( [
		accountBusinessSupportAddress,
	] );
	useAccountBusinessName.mockReturnValue( [ accountBusinessName ] );
	useAccountBusinessURL.mockReturnValue( [ accountBusinessURL ] );
	useAccountBusinessSupportEmail.mockReturnValue( [
		accountBusinessSupportEmail,
	] );
	useAccountBusinessSupportPhone.mockReturnValue( [
		accountBusinessSupportPhone,
	] );
};

const renderPreviewPrintReceipt = async () => {
	const renderResult = render( <PreviewPrintReceipt /> );

	await waitFor( () => {
		expect( apiFetch ).toHaveBeenCalledWith( {
			data: mockSettings,
			method: 'post',
			path: '/wc/v3/payments/readers/receipts/print/preview',
		} );
	} );

	return renderResult;
};

describe( 'PreviewReceipt', () => {
	it( 'should render loading block while fetching from API', async () => {
		mockAccountSettings( mockSettings );

		const { container } = await renderPreviewPrintReceipt();

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render preview when fetch from API succeeds', async () => {
		mockAccountSettings( mockSettings );
		apiFetch.mockResolvedValue( 'test' );

		const { container } = await renderPreviewPrintReceipt();

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render error when fetch from API fails', async () => {
		mockAccountSettings( mockSettings );
		apiFetch.mockRejectedValue( new Error( 'Something bad happened' ) );

		const { container } = await renderPreviewPrintReceipt();

		expect( container ).toMatchSnapshot();
	} );
} );
