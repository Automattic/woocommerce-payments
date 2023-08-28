/** @format **/

/**
 * External dependencies
 */
import React from 'react';
/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import {
	HorizontalList,
	HorizontalListItem,
} from 'wcpay/components/horizontal-list';
import { Charge } from 'wcpay/types/charges';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment/moment';
import { getChargeChannel } from 'wcpay/utils/charge';
import CustomerLink from 'wcpay/components/customer-link';
import OrderLink from 'wcpay/components/order-link';
import PaymentMethodDetails from 'wcpay/components/payment-method-details';
import riskMappings from 'wcpay/components/risk-level/strings';
import isValueTruthy from 'wcpay/utils/is-value-truthy';
import { formatCurrency } from 'wcpay/utils/currency';
import { reasons } from 'wcpay/disputes/strings';
import { formatStringValue } from 'wcpay/utils';
import { smartDueDate } from 'wcpay/disputes';

interface Props {
	dispute: Dispute;
}

const DisputeSummaryRow: React.FC< Props > = ( { dispute } ) => {
	const columns = [
		{
			title: __( 'Dispute Amount', 'woocommerce-payments' ),
			content: formatCurrency( dispute.amount, dispute.currency ),
		},
		{
			title: __( 'Disputed On', 'woocommerce-payments' ),
			content: dispute.created
				? dateI18n(
						'M j, Y, g:ia',
						moment( dispute.created * 1000 ).toISOString()
				  )
				: '–',
		},
		{
			title: __( 'Reason', 'woocommerce-payments' ),
			content: formatStringValue( reasons[ dispute.reason ]?.display ),
		},
		{
			title: __( 'Respond By', 'woocommerce-payments' ),
			content: smartDueDate( dispute ) || '–',
		},
	];

	return <HorizontalList items={ columns } />;
};

export default DisputeSummaryRow;
