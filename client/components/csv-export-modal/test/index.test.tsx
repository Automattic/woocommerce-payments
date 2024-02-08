/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CVSExportModal from '..';
import { useReportingExportLanguage, useSettings } from 'wcpay/data';

declare const global: {
	wcpaySettings: {
		locale: {
			code: string;
			native_name: string;
		};
	};
};

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { updateOptions: jest.fn() } ) ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useReportingExportLanguage: jest.fn( () => [ 'en', jest.fn() ] ),
	useSettings: jest.fn(),
} ) );

const mockUseSettings = useSettings as jest.MockedFunction<
	typeof useSettings
>;

const mockUseReportingExportLanguage = useReportingExportLanguage as jest.MockedFunction<
	typeof useReportingExportLanguage
>;

describe( 'RefundModal', () => {
	beforeEach( () => {
		mockUseReportingExportLanguage.mockReturnValue( [ 'en', jest.fn() ] );

		mockUseSettings.mockReturnValue( {
			isLoading: false,
			isSaving: false,
			saveSettings: ( a ) => a,
		} );

		global.wcpaySettings = {
			locale: {
				code: 'es_ES',
				native_name: 'Spanish',
			},
		};
	} );

	test( 'it renders correctly', () => {
		const { container: modal } = render(
			<CVSExportModal
				onClose={ jest.fn() }
				onSubmit={ jest.fn() }
				totalItems={ 100 }
				exportType={ 'transactions' }
			/>
		);

		expect( modal ).toMatchSnapshot();
	} );
} );
