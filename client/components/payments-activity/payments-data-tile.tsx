/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from '../loadable';
interface PaymentsDataTileProps {
	id: string; // id of the element can be used for CSS styling
	label: string; // The label appears as title of the tile
	currencyCode: string; // The currency to be displayed in the tile
	tooltip?: React.ReactElement; // For optionally passing the ClickTooltip component
	amount?: number; // The amount to be displayed in the tile
	isLoading?: boolean; // Loading state of the tile
	reportLink?: string; // Optional hover link to view report
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
	return (
		<div id={ id } className="wcpay-payments-data-highlights__item">
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
				{ reportLink && (
					<a
						href={ reportLink }
						className="wcpay-payments-data-highlights__item__wrapper__report-link"
					>
						{ __( 'View report', 'woocommerce_payments' ) }
					</a>
				) }
			</div>
		</div>
	);
};

export default PaymentsDataTile;
