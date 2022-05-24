/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PlatformCheckoutFileUpload from '../file-upload';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createErrorNotice: jest.fn() } ) ),
} ) );

describe( 'PlatformCheckoutFileUpload', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			restUrl: 'http://example.com/wp-json/',
		};
	} );

	it( 'should render replace and delete button with file', () => {
		const { container } = render(
			<PlatformCheckoutFileUpload
				fieldKey="test"
				label="test"
				accept="image/png"
				disabled={ false }
				purpose="branding_logo"
				fileID="123"
			/>
		);

		expect(
			container.firstChild.firstChild.classList.contains( 'has-file' )
		).toBe( true );

		expect(
			screen.queryByRole( 'button', { name: 'Replace' } )
		).toBeInTheDocument();

		expect(
			screen.queryByRole( 'button', { name: 'Remove file' } )
		).toBeInTheDocument();
	} );

	it( 'should not render replace and delete button without file', () => {
		const { container } = render(
			<PlatformCheckoutFileUpload
				fieldKey="test"
				label="test"
				accept="image/png"
				disabled={ false }
				purpose="branding_logo"
				fileID={ null }
			/>
		);

		expect(
			container.firstChild.firstChild.classList.contains( 'has-file' )
		).toBe( false );

		expect(
			screen.queryByRole( 'button', { name: 'Upload custom logo' } )
		).toBeInTheDocument();

		expect(
			screen.queryByRole( 'button', { name: 'Replace' } )
		).not.toBeInTheDocument();

		expect(
			screen.queryByRole( 'button', { name: 'Remove file' } )
		).not.toBeInTheDocument();
	} );
} );
