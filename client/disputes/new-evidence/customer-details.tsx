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
	const phone = charge?.billing_details?.phone || '-';
	const email = charge?.billing_details?.email || '-';
	const ip = dispute.order?.ip_address || '-';
	const address = charge?.billing_details?.address;
	const billingLine1 = address?.line1 || '';
	const billingLine2 = address?.line2 || '';
	const billingCity = address?.city || '';
	const billingState = address?.state || '';
	const billingPostcode = address?.postal_code || '';
	const billingCountry = address?.country || '';
	const billingAddress = `${ billingLine1 }, ${ billingLine2 }, ${ billingCity }, ${ billingState }, ${ billingPostcode }, ${ billingCountry }`;
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
					<span>{ phone }</span>
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
					<span>{ ip }</span>
				</div>
			</div>
			<div className="wcpay-dispute-evidence-customer-details__billing">
				<div className="wcpay-dispute-evidence-customer-details__billing-label">
					{ __( 'BILLING ADDRESS', 'woocommerce-payments' ) }
				</div>
				<div className="wcpay-dispute-evidence-customer-details__billing-value">
					{ billingAddress }
				</div>
			</div>
		</section>
	);
};

export default CustomerDetails;
