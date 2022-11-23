/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BusinessDetailsSection from '../business-details';
import ContactsDetailsSection from '../contacts-details';
import AddressDetailsSection from '../address-details';
import BrandingDetailsSection from '../branding-details';
import BrandingFileUpload from '../../file-upload';

jest.mock( '../../file-upload', () => jest.fn() );

const setDisabledMock = jest.fn();

describe( 'Card Reader Business Details section', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			connect: {
				country: 'US',
				availableCountries: { US: 'United States (US)' },
				availableStates: [
					{
						US: [ 'Florida', 'California', 'Texas' ],
					},
				],
			},
		};
	} );

	test( 'Renders Business section', () => {
		render(
			<BusinessDetailsSection setSaveDisabled={ setDisabledMock } />
		);

		const heading = screen.queryByRole( 'heading', {
			name: 'Business details',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'Renders Business settings', () => {
		render(
			<BusinessDetailsSection setSaveDisabled={ setDisabledMock } />
		);

		const name = screen.getByLabelText( 'Business name' );
		expect( name ).toBeInTheDocument();

		const url = screen.getByLabelText( 'Business URL' );
		expect( url ).toBeInTheDocument();
	} );
} );

describe( 'Card Reader Contact Details section', () => {
	test( 'Renders Contacts section', () => {
		render(
			<ContactsDetailsSection setSaveDisabled={ setDisabledMock } />
		);

		const heading = screen.queryByRole( 'heading', {
			name: 'Customer support contacts',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'Renders Contacts settings', () => {
		render(
			<ContactsDetailsSection setSaveDisabled={ setDisabledMock } />
		);

		const email = screen.getByLabelText( 'Support email' );
		expect( email ).toBeInTheDocument();

		const phone = screen.getByLabelText( 'Support phone number' );
		expect( phone ).toBeInTheDocument();
	} );
} );

describe( 'Card Reader Address Details section', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcSettings = {
			countries: {
				US: 'United States of America',
			},
		};
	} );

	afterEach( () => {
		delete global.wcSettings;
	} );

	test( 'Renders Address section', () => {
		render( <AddressDetailsSection /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Business address',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'Renders Address settings', () => {
		render( <AddressDetailsSection /> );

		const country = screen.getByLabelText( 'Country' );
		expect( country ).toBeInTheDocument();

		const address1 = screen.getByLabelText( 'Address line 1' );
		expect( address1 ).toBeInTheDocument();

		const address2 = screen.getByLabelText( 'Address line 2' );
		expect( address2 ).toBeInTheDocument();

		const city = screen.getByLabelText( 'City' );
		expect( city ).toBeInTheDocument();

		const zip = screen.getByLabelText( 'Postal code' );
		expect( zip ).toBeInTheDocument();
	} );
} );

describe( 'Card Reader Branding Details section', () => {
	beforeEach( () => {
		BrandingFileUpload.mockReturnValue( <div>File Upload</div> );
	} );

	test( 'Renders Contacts section', () => {
		render( <BrandingDetailsSection /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Branding',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'Renders Contacts settings', () => {
		render( <BrandingDetailsSection /> );

		const fileUploadFields = screen.queryAllByText( 'File Upload' );
		expect( fileUploadFields.length ).toEqual( 1 );
	} );
} );
