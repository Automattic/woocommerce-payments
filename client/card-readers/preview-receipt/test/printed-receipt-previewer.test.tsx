/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import PrintedReceiptPreviewer from '../previewer';

describe( 'PrintedReceiptPreviewer', () => {
	it( 'should render correctly', () => {
		const expected = 'test';
		const { container, getByTitle } = render(
			<PrintedReceiptPreviewer receiptHtml={ expected } />
		);
		const iframeElement = getByTitle(
			'Preview Receipt'
		) as HTMLIFrameElement;

		expect( iframeElement?.contentWindow?.document.body.innerHTML ).toEqual(
			`<div>${ expected }</div>`
		);
		expect( container ).toMatchSnapshot();
	} );
} );
