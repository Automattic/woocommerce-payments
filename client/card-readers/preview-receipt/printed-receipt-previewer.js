/**
 * External dependencies
 */
import { useState } from '@wordpress/element';
import React from 'react';
import { createPortal } from 'react-dom';

/**
 * Internal dependencies
 */
import './style.scss';

const IFrameComponent = ( { title, children } ) => {
	const [ contentRef, setContentRef ] = useState( null );
	const mountNode = contentRef?.contentWindow?.document?.body;

	return (
		<iframe
			title={ title }
			ref={ setContentRef }
			className="card-readers-preview-receipt__preview"
		>
			{ mountNode && createPortal( children, mountNode ) }
		</iframe>
	);
};

function prepareMarkup( __html = '' ) {
	return { __html };
}

const PrintedReceiptPreviewer = ( { receiptHtml } ) => {
	return (
		<IFrameComponent title="Preview Receipt">
			<div dangerouslySetInnerHTML={ prepareMarkup( receiptHtml ) } />
		</IFrameComponent>
	);
};

export default PrintedReceiptPreviewer;
