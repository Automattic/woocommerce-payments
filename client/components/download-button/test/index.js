/** @format */
/**
 * External dependencies
 */
import { render, fireEvent, getByRole } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DownloadButton from '../';

describe( 'DownloadButton', () => {
	test( 'renders an active button', () => {
		const onDownload = jest.fn();
		const { container: button } = renderDownloadButton( false, onDownload );

		fireEvent(
			getByRole( button, 'button' ),
			new MouseEvent( 'click', {
				bubbles: true,
				cancelable: true,
			} )
		);

		expect( onDownload.mock.calls.length ).toBe( 1 );
		expect( button ).toMatchSnapshot();
	} );

	test( 'renders a disabled button', () => {
		const onDownload = jest.fn();
		const { container: button } = renderDownloadButton( true, onDownload );

		fireEvent(
			getByRole( button, 'button' ),
			new MouseEvent( 'click', {
				bubbles: true,
				cancelable: true,
			} )
		);

		expect( onDownload.mock.calls.length ).toBe( 0 );
		expect( button ).toMatchSnapshot();
	} );

	function renderDownloadButton( isDisabled, onClick ) {
		return render(
			<DownloadButton isDisabled={ isDisabled } onClick={ onClick } />
		);
	}
} );
