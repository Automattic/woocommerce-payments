/**
 * External dependencies
 */
import * as React from 'react';
import { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from '../loadable';
interface PaymentsDataTileProps {
	id: string;
	label: string;
	currencyCode: string;
	tooltip?: React.ReactElement;
	amount?: number;
	isLoading?: boolean;
	reportLink?: string;
}

const PaymentsDataTile: React.FC< PaymentsDataTileProps > = ( {
	id,
	label,
	currencyCode,
	tooltip,
	amount = 0,
	isLoading = false,
	reportLink,
} ) => {
	const [ showReportLink, setShowReportLink ] = useState( false );

	return (
		<div
			id={ id }
			className="wcpay-payments-data-highlights__item"
			onMouseEnter={ () => setShowReportLink( true ) }
			onMouseLeave={ () => setShowReportLink( false ) }
		>
			<p className="wcpay-payments-data-highlights__item__label">
				<span>{ label }</span>
				{ ! isLoading && tooltip }
			</p>
			<div className="wcpay-payments-data-highlights__item__wrapper">
				<p
					className="wcpay-payments-data-highlights__item__wrapper__amount"
					aria-labelledby={ id }
				>
					<Loadable
						isLoading={ isLoading }
						display="inline"
						placeholder="loading amount"
						value={ formatCurrency( amount, currencyCode ) }
					/>
				</p>
				{ reportLink && showReportLink && (
					<a
						href={ reportLink }
						className="wcpay-payments-data-highlights__item__wrapper__report-link"
					>
						{ __( 'View Report', 'woocommerce_payments' ) }
					</a>
				) }
			</div>
		</div>
	);
};

export default PaymentsDataTile;
