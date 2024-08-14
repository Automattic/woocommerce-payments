/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { recordEvent } from 'wcpay/tracks';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from '../loadable';
import './style.scss';

interface PaymentDataTileProps {
	/**
	 * The id for the tile, can be used for CSS styling.
	 */
	id: string;
	/**
	 * Label for the amount in the tile.
	 */
	label: string;
	/**
	 * The currency code for the amount displayed.
	 */
	currencyCode?: string;
	/**
	 * For optionally passing a ClickTooltip component.
	 */
	tooltip?: React.ReactElement;
	/**
	 * The amount to be displayed in the tile.
	 */
	amount?: number;
	/**
	 * Loading state of the tile.
	 */
	isLoading?: boolean;
	/**
	 * Optional hover link to view report.
	 */
	reportLink?: string;
	/**
	 * The source of the event tracking.
	 */
	tracksSource?: string;
}

const PaymentDataTile: React.FC< PaymentDataTileProps > = ( {
	id,
	label,
	currencyCode,
	tooltip,
	amount = 0,
	isLoading = false,
	reportLink,
	tracksSource,
} ) => {
	const handleReportLinkClick = () => {
		recordEvent( 'wcpay_overview_payment_activity_click', {
			source: tracksSource,
		} );
	};
	return (
		<div className="wcpay-payment-data-highlights__item">
			<p className="wcpay-payment-data-highlights__item__label">
				<span id={ id }>{ label }</span>
				{ ! isLoading && tooltip }
			</p>
			<div className="wcpay-payment-data-highlights__item__wrapper">
				<p
					className="wcpay-payment-data-highlights__item__wrapper__amount"
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
					<Link href={ reportLink } onClick={ handleReportLinkClick }>
						{ __( 'View report', 'woocommerce-payments' ) }
					</Link>
				) }
			</div>
		</div>
	);
};

export default PaymentDataTile;
