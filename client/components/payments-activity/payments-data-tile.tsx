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

/**
 * Props for the PaymentsDataHighlightBlock component.
 *
 * @typedef {Object} PaymentsDataTileProps
 *
 * @property {string} id					The payment data highlight block id. Used to link the title and amount.
 * @property {string} title					The payment data highlight block title.
 * @property {string} currencyCode			Currency code of the payment data highlight block.
 * @property {React.ReactElement} tooltip	The tooltip element.
 * @property {number} [amount]				Optional. The payment data highlight block amount.
 * @property {boolean} [isLoading]			Optional. Whether the payment data highlight block is loading.
 * @property {string} [reportLink]			Optional. The report link.
 */
interface PaymentsDataTileProps {
	id: string;
	title: string;
	currencyCode: string;
	tooltip?: React.ReactElement;
	amount?: number;
	isLoading?: boolean;
	reportLink?: string;
}

/**
 * Renders a block that highlights payment data.
 *
 * @param {PaymentsDataTileProps} props   Payment data highlight block props. See `PaymentDataHighlightBlockProps` interface.
 *
 * @return {JSX.Element} Rendered payment data highlight block element.
 */
const PaymentsDataTile: React.FC< PaymentsDataTileProps > = ( {
	id,
	title,
	currencyCode,
	tooltip,
	amount = 0,
	isLoading = false,
	reportLink = '#',
} ) => {
	return (
		<div id={ id } className="wcpay-payments-data-highlights__item">
			<p className="wcpay-payments-data-highlights__item__label">
				<span>{ title }</span>
				{ ! isLoading && tooltip }
			</p>
			<p
				className="wcpay-payments-data-highlights__item__amount"
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
					className="wcpay-payments-data-highlights__item__report-link"
				>
					{ __( 'View Report', 'woocommerce_payments' ) }
				</a>
			) }
		</div>
	);
};

export default PaymentsDataTile;
