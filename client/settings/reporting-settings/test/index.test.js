/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Reporting from '..';
import { useReportingExportLanguage } from 'wcpay/data';

jest.mock( '@wordpress/data' );

jest.mock( 'wcpay/data', () => ( {
	useReportingExportLanguage: jest.fn(),
} ) );

describe( 'Deposits', () => {
	beforeEach( () => {
		useReportingExportLanguage.mockReturnValue( [ 'en_US', jest.fn() ] );

		global.wcpaySettings = {
			locale: {
				code: 'es_ES',
				native_name: 'Spanish',
			},
		};
	} );

	it( 'should render correctly', () => {
		const { container } = render( <Reporting /> );
		expect( container ).toMatchSnapshot();

		expect(
			screen.getByText( /Report exporting default language/ )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				/You can change your global site language preferences/
			)
		).toBeInTheDocument();
	} );

	it( 'renders the language select', () => {
		render( <Reporting /> );

		const frequencySelect = screen.getByLabelText( /Language/ );
		expect( frequencySelect ).toHaveValue( 'en_US' );

		within( frequencySelect ).getByRole( 'option', { name: /English/ } );
		within( frequencySelect ).getByRole( 'option', {
			name: /Site Language - Spanish/,
		} );
	} );
} );
