/**
 * External dependencies
 */
import { useEffect, useRef, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * The response from the export POST request, which returns an export ID.
 */
interface ExportResponse {
	/**
	 * The export ID, used to check if the export is ready to be downloaded when polling.
	 */
	export_id?: string;
}

/**
 * The response from the export URL GET request, which returns the URL to download the file.
 */
interface ExportURLResponse {
	status: string;
	download_url?: string;
}

const maxRetries = 5;

/**
 * Hook for requesting and polling for a CSV export. E.g. Transactions, Payouts, Disputes.
 *
 * @example
 * const { requestReportExport, isExportInProgress } = useReportExport();
 * requestReportExport( {
 * 	exportRequestURL: '/wc/v3/payments/transactions/download?queryParam=value',
 * 	exportFileAvailabilityEndpoint: '/wc/v3/payments/transactions/download',
 * 	userEmail: 'test@example.com',
 * } );
 */
export const useReportExport = () => {
	const [ isExportInProgress, setIsExportInProgress ] = useState( false );
	const { createNotice } = useDispatch( 'core/notices' );
	const timeoutIdRef = useRef< NodeJS.Timeout | null >( null );
	const retryCountRef = useRef( 0 );

	useEffect( () => {
		// Cancel the timeout if the component unmounts.
		return () => {
			if ( timeoutIdRef.current ) {
				clearTimeout( timeoutIdRef.current );
			}
		};
	}, [ timeoutIdRef ] );

	interface PollForFileProps {
		/**
		 * The URL to check for the exported file. E.g. "/wc/v3/payments/transactions/download/{export_id}"
		 */
		checkFileURL: string;
		/**
		 * The email address the export will be sent to.
		 */
		userEmail: string;
		/**
		 * The interval in milliseconds to poll the server.
		 */
		interval?: number;
	}

	/**
	 * Polls the server to check if the exported file is ready.
	 */
	async function pollForFile( {
		checkFileURL,
		userEmail,
		interval = 1000,
	}: PollForFileProps ) {
		timeoutIdRef.current = setTimeout( async () => {
			retryCountRef.current++;
			let exportedFileURLResponse;
			try {
				exportedFileURLResponse = await apiFetch< ExportURLResponse >( {
					path: checkFileURL,
					method: 'GET',
				} );
			} catch ( error ) {
				// If there's an error (like 500), continue with retry logic
				exportedFileURLResponse = { status: 'error' };
			}

			if (
				'success' === exportedFileURLResponse.status &&
				exportedFileURLResponse.download_url
			) {
				// The file is available, so we can download it.
				// Create a link element to trigger the download.
				const link = document.createElement( 'a' );
				// Add force_download=true to the URL to force the download, which adds the appropriate `Content-Disposition: attachment` header when using production server.
				link.href =
					exportedFileURLResponse.download_url +
					'?force_download=true';
				link.click();

				setIsExportInProgress( false );

				return;
			}

			if ( retryCountRef.current < maxRetries ) {
				// If the file is not available, check again after 1 second.
				pollForFile( {
					checkFileURL,
					userEmail,
				} );
			} else {
				// If the file is not available after the maximum number of retries, show that it will be emailed.
				setIsExportInProgress( false );
			}
		}, interval );
	}

	interface RequestReportExportProps {
		/**
		 * The URL for requesting the export. E.g. "/wc/v3/payments/transactions/download?param=value"
		 */
		exportRequestURL: string;
		/**
		 * The endpoint for retrieving the file's download URL. E.g. "/wc/v3/payments/transactions/download/"
		 */
		exportFileAvailabilityEndpoint: string;
		/**
		 * The email address to send the export to.
		 */
		userEmail: string;
	}

	/**
	 * Requests a CSV export to be prepared.
	 */
	async function requestReportExport( {
		exportRequestURL,
		exportFileAvailabilityEndpoint,
		userEmail,
	}: RequestReportExportProps ) {
		try {
			setIsExportInProgress( true );

			// Request the report download.
			const response = await apiFetch< ExportResponse >( {
				path: exportRequestURL,
				method: 'POST',
			} );

			// If the export request was successful, start polling to see if the exported file is ready.
			if ( response.export_id ) {
				const checkFileURL = `${ exportFileAvailabilityEndpoint }/${ response.export_id }`;
				pollForFile( {
					checkFileURL,
					userEmail,
				} );
			}
		} catch ( error ) {
			setIsExportInProgress( false );
			createNotice(
				'error',
				__(
					'There was a problem generating your export.',
					'woocommerce-payments'
				)
			);
		}
	}

	return {
		/**
		 * Requests a report export to be prepared and starts polling for the exported file.
		 */
		requestReportExport,
		/**
		 * Whether a report download request has been made and polling is in progress.
		 */
		isExportInProgress,
	};
};
