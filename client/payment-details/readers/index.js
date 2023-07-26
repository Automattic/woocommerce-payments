/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
	generateCSVFileName,
} from '@woocommerce/csv-export';
import { TableCard } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { useCardReaderStats } from 'wcpay/data';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import Page from 'components/page';
import DownloadButton from 'components/download-button';
import * as React from 'react';
import { formatExplicitCurrency } from 'utils/currency';

const PaymentCardReaderChargeDetails = ( props ) => {
	const { readers, chargeError, isLoading } = useCardReaderStats(
		props.chargeId,
		props.transactionId
	);
	const testModeNotice = <TestModeNotice topic={ topics.paymentDetails } />;

	// Check instance of chargeError because its default value is empty object
	if ( ! isLoading && chargeError instanceof Error ) {
		return (
			<Page maxWidth={ 1032 } className="wcpay-payment-details">
				{ testModeNotice }
				<Card>
					<CardBody>
						{ __(
							'Readers details not loaded',
							'woocommerce-payments'
						) }
					</CardBody>
				</Card>
			</Page>
		);
	}

	return (
		<RenderPaymentCardReaderChargeDetails
			readers={ readers }
			isLoading={ isLoading }
		/>
	);
};

const RenderPaymentCardReaderChargeDetails = ( props ) => {
	const readers = props.readers;
	const isLoading = props.isLoading;
	const testModeNotice = <TestModeNotice topic={ topics.paymentDetails } />;

	const headers = [
		{
			key: 'reader_id',
			label: __( 'Reader id', 'woocommerce-payments' ),
			visible: true,
			isLeftAligned: true,
		},
		{
			key: 'status',
			label: __( 'Status', 'woocommerce-payments' ),
			visible: true,
			isLeftAligned: true,
		},
		{
			key: 'count',
			label: __( 'Transactions', 'woocommerce-payments' ),
			visible: true,
			isLeftAligned: true,
		},
		{
			key: 'fee',
			label: __( 'Fee', 'woocommerce-payments' ),
			visible: true,
			isLeftAligned: true,
		},
	];

	const rows =
		0 < readers.length
			? readers.map( ( reader ) => {
					return [
						{
							value: reader.reader_id,
							display: reader.reader_id,
						},
						{
							value: reader.status,
							display: reader.status,
						},
						{
							value: reader.count,
							display: reader.count,
						},
						{
							value: reader.fee ? reader.fee.amount / 100 : 0,
							display: reader.fee
								? formatExplicitCurrency(
										reader.fee.amount,
										reader.fee.currency
								  )
								: 0,
						},
					];
			  } )
			: [];

	const onDownload = () => {
		// We destructure page and path to get the right params.
		// eslint-disable-next-line @typescript-eslint/no-unused-vars
		const { page, path, ...params } = getQuery();

		downloadCSVFile(
			generateCSVFileName( 'Card Readers', params ),
			generateCSVDataFromTable( headers, rows )
		);
	};

	const downloadable = !! rows.length;
	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			{ testModeNotice }
			<TableCard
				className="transactions-list woocommerce-report-table has-search"
				title={ __( 'Card readers', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
				totalRows={ rows.length }
				headers={ headers }
				rows={ rows }
				actions={ [
					downloadable && (
						<DownloadButton
							key="download"
							isDisabled={ isLoading }
							onClick={ onDownload }
						/>
					),
				] }
			/>
		</Page>
	);
};

export default PaymentCardReaderChargeDetails;
