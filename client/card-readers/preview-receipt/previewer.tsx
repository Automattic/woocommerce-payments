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

interface PrintedReceiptPreviewerProps {
	receiptHtml: string;
}

interface IFrameComponentProps {
	title: string;
	children: React.ReactNode;
}

const IFrameComponent = ( { title, children }: IFrameComponentProps ) => {
	const [ contentRef, setContentRef ] = useState< HTMLIFrameElement | null >(
		null
	);
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

const PrintedReceiptPreviewer = ( {
	receiptHtml,
}: PrintedReceiptPreviewerProps ): JSX.Element => {
	return (
		<IFrameComponent title="Preview Receipt">
			{
				// eslint-disable-next-line react/no-danger
				<div dangerouslySetInnerHTML={ { __html: receiptHtml } } />
			}
		</IFrameComponent>
	);
};

export default PrintedReceiptPreviewer;
