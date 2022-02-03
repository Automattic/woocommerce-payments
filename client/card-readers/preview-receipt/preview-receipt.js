/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';
/**
 * Internal dependencies
 */
import { LoadableBlock } from '../../components/loadable';
import PrintedReceiptPreviewer from 'wcpay/card-readers/preview-receipt/printed-receipt-previewer';
import {
	useAccountBusinessSupportAddress,
	useAccountBusinessName,
	useAccountBusinessURL,
	useAccountBusinessSupportEmail,
	useAccountBusinessSupportPhone,
} from '../../data';

async function fetchReceiptHtml( payload ) {
	const path = '/wc/v3/payments/readers/receipts/print/preview';
	return apiFetch( { path, data: payload, method: 'post' } );
}

const PreviewReceipt = () => {
	const [ receiptHtml, setReceiptHtml ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isErrorFetchingReceipt, setIsErrorFetchingReceipt ] = useState(
		false
	);

	const [
		accountBusinessSupportAddress,
	] = useAccountBusinessSupportAddress();
	const [ accountBusinessName ] = useAccountBusinessName();
	const [ accountBusinessURL ] = useAccountBusinessURL();
	const [ accountBusinessSupportEmail ] = useAccountBusinessSupportEmail();
	const [ accountBusinessSupportPhone ] = useAccountBusinessSupportPhone();

	useEffect( () => {
		let didCancel = false;
		async function fetchReceiptHtmlAPI() {
			try {
				const data = await fetchReceiptHtml( {
					accountBusinessSupportAddress,
					accountBusinessName,
					accountBusinessURL,
					accountBusinessSupportEmail,
					accountBusinessSupportPhone,
				} );

				if ( ! didCancel && data ) {
					setIsLoading( false );
					setReceiptHtml( data );
				}
			} catch ( error ) {
				setIsLoading( false );
				setIsErrorFetchingReceipt( true );
			}
		}
		fetchReceiptHtmlAPI();
		return () => {
			didCancel = true;
		};
	}, [
		accountBusinessName,
		accountBusinessSupportAddress,
		accountBusinessSupportEmail,
		accountBusinessSupportPhone,
		accountBusinessURL,
	] );

	return (
		<>
			{ isLoading && (
				<p>{ __( 'Generating preview.', 'woocommerce-payments' ) }</p>
			) }
			<LoadableBlock isLoading={ isLoading } numLines={ 25 }>
				{ ! isErrorFetchingReceipt && (
					<PrintedReceiptPreviewer receiptHtml={ receiptHtml } />
				) }
				{ isErrorFetchingReceipt && (
					<Notice status="error" isDismissible={ false }>
						{ __(
							'There was a problem generating the receipt preview. Please try again.',
							'woocommerce-payments'
						) }
					</Notice>
				) }
			</LoadableBlock>
		</>
	);
};

export default PreviewReceipt;
