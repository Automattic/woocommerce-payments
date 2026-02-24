/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Dispute } from 'wcpay/types/disputes';
import CustomerLink from 'wcpay/components/customer-link';

interface CustomerDetailsProps {
	dispute: Dispute;
}

const CustomerDetails: React.FC< CustomerDetailsProps > = ( { dispute } ) => {
	if ( ! dispute ) return null;
	const charge =
		typeof dispute.charge === 'object' && dispute.charge !== null
			? dispute.charge
			: null;
	const name = charge?.billing_details?.name || '-';
	const email = charge?.billing_details?.email || '-';

	return (
		<section className="wcpay-dispute-evidence-customer-details">
			<h3 className="wcpay-dispute-evidence-customer-details__heading">
				{ __( 'Customer details', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-customer-details__row">
				<div>
					<div className="wcpay-dispute-evidence-customer-details__label">
						{ __( 'NAME', 'woocommerce-payments' ) }
					</div>
					{ name !== '-' ? (
						<CustomerLink
							className="wcpay-dispute-evidence-customer-details__link"
							billing_details={ charge?.billing_details || null }
							order_details={ charge?.order || null }
						/>
					) : (
						<span>{ name }</span>
					) }
				</div>
				<div>
					<div className="wcpay-dispute-evidence-customer-details__label">
						{ __( 'PHONE', 'woocommerce-payments' ) }
					</div>
					<span className="wcpay-dispute-evidence-customer-details__phone-number">
						{ charge?.billing_details?.phone || '-' }
					</span>
				</div>
				<div>
					<div className="wcpay-dispute-evidence-customer-details__label">
						{ __( 'EMAIL', 'woocommerce-payments' ) }
					</div>
					{ email !== '-' ? (
						<a
							href={ `mailto:${ email }` }
							className="wcpay-dispute-evidence-customer-details__link"
						>
							{ email }
						</a>
					) : (
						<span>{ email }</span>
					) }
				</div>
				<div>
					<div className="wcpay-dispute-evidence-customer-details__label">
						{ __( 'IP ADDRESS', 'woocommerce-payments' ) }
					</div>
					<span className="wcpay-dispute-evidence-customer-details__ip-address">
						{ dispute.order?.ip_address || '-' }
					</span>
				</div>
			</div>
			<div className="wcpay-dispute-evidence-customer-details__billing">
				<div className="wcpay-dispute-evidence-customer-details__billing-label">
					{ __( 'BILLING ADDRESS', 'woocommerce-payments' ) }
				</div>
				<div className="wcpay-dispute-evidence-customer-details__billing-value">
					{ charge?.billing_details?.formatted_address || '-' }
				</div>
			</div>
		</section>
	);
};

export default CustomerDetails;
