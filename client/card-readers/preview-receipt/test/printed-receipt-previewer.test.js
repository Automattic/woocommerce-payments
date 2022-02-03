/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PrintedReceiptPreviewer from '../printed-receipt-previewer';

describe( 'PrintedReceiptPreviewer', () => {
	it( 'should render correctly', () => {
		const expected = 'test';
		const { container, getByTitle } = render(
			<PrintedReceiptPreviewer receiptHtml={ expected } />
		);
		const iframeElement = getByTitle( 'Preview Receipt' );

		expect( iframeElement.contentWindow.document.body.innerHTML ).toEqual(
			`<div>${ expected }</div>`
		);
		expect( container ).toMatchSnapshot();
	} );
} );
