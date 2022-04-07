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
	const [ iframeBody, setIframeBody ] = useState< HTMLElement | null >(
		null
	);
	const handleLoad = ( event: React.SyntheticEvent ) => {
		const iframe = event.target as HTMLIFrameElement;
		if ( iframe?.contentDocument ) {
			setIframeBody( iframe.contentDocument.body );
		}
	};

	return (
		<iframe
			className="card-readers-preview-receipt__preview"
			srcDoc={ `<!DOCTYPE html>` }
			title={ title }
			onLoad={ handleLoad }
		>
			{ iframeBody && createPortal( children, iframeBody ) }
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
