/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import { formatCurrency } from 'wcpay/utils/currency';
import Loadable from '../loadable';

/**
 * Props for the PaymentDataHighlightBlock component.
 *
 * @typedef {Object} PaymentDataTileProps
 *
 * @property {string} id					The payment data highlight block id. Used to link the title and amount.
 * @property {string} title					The payment data highlight block title.
 * @property {string} currencyCode			Currency code of the payment data highlight block.
 * @property {React.ReactElement} tooltip	The tooltip element.
 * @property {number} [amount]				Optional. The payment data highlight block amount.
 * @property {boolean} [isLoading]			Optional. Whether the payment data highlight block is loading.
 */
interface PaymentDataTileProps {
	id: string;
	title: string;
	currencyCode: string;
	tooltip?: React.ReactElement;
	amount?: number;
	isLoading?: boolean;
}

/**
 * Renders a block that highlights payment data.
 *
 * @param {PaymentDataTileProps} props   Payment data highlight block props. See `PaymentDataHighlightBlockProps` interface.
 *
 * @return {JSX.Element} Rendered payment data highlight block element.
 */
const PaymentsDataTile: React.FC< PaymentDataTileProps > = ( {
	id,
	title,
	currencyCode,
	tooltip,
	amount = 0,
	isLoading = false,
} ) => {
	return (
		<div className="payments-data-highlights__item">
			<p id={ id } className="payments-data-highlights__item__title">
				<span>{ title }</span>
				{ ! isLoading && tooltip }
			</p>
			<p
				className="payments-data-highlights__item__amount"
				aria-labelledby={ id }
			>
				<Loadable
					isLoading={ isLoading }
					display="inline"
					placeholder="loading amount"
					value={ formatCurrency( amount, currencyCode ) }
				/>
			</p>
		</div>
	);
};

export default PaymentsDataTile;
