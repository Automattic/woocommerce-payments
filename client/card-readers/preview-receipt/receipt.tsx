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
import { LoadableBlock } from 'components/loadable';
import PrintedReceiptPreviewer from 'wcpay/card-readers/preview-receipt/previewer';
import {
	useAccountBusinessSupportAddress,
	useAccountBusinessName,
	useAccountBusinessURL,
	useAccountBusinessSupportEmail,
	useAccountBusinessSupportPhone,
} from '../../data';
import { FetchReceiptPayload } from 'wcpay/types/card-readers';

async function fetchReceiptHtml(
	payload: FetchReceiptPayload
): Promise< string > {
	const path = '/wc/v3/payments/readers/receipts/preview';
	return apiFetch( { path, data: payload, method: 'post' } );
}

const PreviewReceipt = (): JSX.Element => {
	const [ receiptHtml, setReceiptHtml ] = useState< string >( '' );
	const [ isLoading, setIsLoading ] = useState< boolean >( true );
	const [ isErrorFetchingReceipt, setIsErrorFetchingReceipt ] = useState<
		boolean
	>( false );

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
					accountBusinessName,
					accountBusinessSupportAddress,
					accountBusinessURL,
					accountBusinessSupportEmail,
					accountBusinessSupportPhone,
				} as FetchReceiptPayload );

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
				{ isErrorFetchingReceipt && (
					<Notice status="error" isDismissible={ false }>
						{ __(
							'There was a problem generating the receipt preview. Please try again later.',
							'woocommerce-payments'
						) }
					</Notice>
				) }
				{ ! isErrorFetchingReceipt && (
					<PrintedReceiptPreviewer receiptHtml={ receiptHtml } />
				) }
			</LoadableBlock>
		</>
	);
};

export default PreviewReceipt;
