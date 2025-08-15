/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React, { act } from 'react';

/**
 * Internal dependencies
 */
import { useReaders, useSettings } from 'wcpay/data';
import ConnectedReaders from '..';

// Mock the data hooks
jest.mock( 'wcpay/data', () => ( {
	useReaders: jest.fn(),
	useSettings: jest.fn(),
} ) );

const mockUseReaders = useReaders as jest.MockedFunction< typeof useReaders >;
const mockUseSettings = useSettings as jest.MockedFunction<
	typeof useSettings
>;

describe( 'CardReadersSettings', () => {
	beforeEach( () => {
		// Mock the useReaders hook to return empty readers list
		mockUseReaders.mockReturnValue( {
			readers: [],
			isLoading: false,
		} );

		// Mock the useSettings hook to return not loading
		mockUseSettings.mockReturnValue( {
			isLoading: false,
			saveSettings: jest.fn(),
			isSaving: false,
			isDirty: false,
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'Card Readers tabs renders', async () => {
		await act( async () => {
			render( <ConnectedReaders /> );
		} );

		expect( screen.queryByText( 'Connected readers' ) ).toBeInTheDocument();
	} );
} );
