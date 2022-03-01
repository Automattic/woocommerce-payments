/**
 * External dependencies
 */
import { render, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import React from 'react';

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
import { FetchReceiptPayload } from 'wcpay/types/card-readers';
import PreviewPrintReceipt from '..';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( 'wcpay/data', () => ( {
	useAccountBusinessSupportAddress: jest.fn(),
	useAccountBusinessName: jest.fn(),
	useAccountBusinessURL: jest.fn(),
	useAccountBusinessSupportEmail: jest.fn(),
	useAccountBusinessSupportPhone: jest.fn(),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;
const mockUseAccountBusinessSupportAddress = useAccountBusinessSupportAddress as jest.MockedFunction<
	typeof useAccountBusinessSupportAddress
>;
const mockUseAccountBusinessName = useAccountBusinessName as jest.MockedFunction<
	typeof useAccountBusinessName
>;
const mockUseAccountBusinessURL = useAccountBusinessURL as jest.MockedFunction<
	typeof useAccountBusinessURL
>;
const mockUseAccountBusinessSupportEmail = useAccountBusinessSupportEmail as jest.MockedFunction<
	typeof useAccountBusinessSupportEmail
>;
const mockUseAccountBusinessSupportPhone = useAccountBusinessSupportPhone as jest.MockedFunction<
	typeof useAccountBusinessSupportPhone
>;

const mockSettings = {
	accountBusinessSupportAddress: {
		line1: 'line1',
		line2: 'line2',
		city: 'city',
		postal_code: '42',
		country: 'US',
		state: 'state',
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
}: FetchReceiptPayload ) => {
	mockUseAccountBusinessSupportAddress.mockReturnValue( [
		accountBusinessSupportAddress,
	] );
	mockUseAccountBusinessName.mockReturnValue( [ accountBusinessName ] );
	mockUseAccountBusinessURL.mockReturnValue( [ accountBusinessURL ] );
	mockUseAccountBusinessSupportEmail.mockReturnValue( [
		accountBusinessSupportEmail,
	] );
	mockUseAccountBusinessSupportPhone.mockReturnValue( [
		accountBusinessSupportPhone,
	] );
};

const renderPreviewPrintReceipt = async () => {
	const renderResult = render( <PreviewPrintReceipt /> );

	await waitFor( () => {
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			data: mockSettings,
			method: 'post',
			path: '/wc/v3/payments/readers/receipts/preview',
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
		mockApiFetch.mockResolvedValue( 'test' );

		const { container } = await renderPreviewPrintReceipt();

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render error when fetch from API fails', async () => {
		mockAccountSettings( mockSettings );
		mockApiFetch.mockRejectedValue( new Error( 'Something bad happened' ) );

		const { container } = await renderPreviewPrintReceipt();

		expect( container ).toMatchSnapshot();
	} );
} );
