/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

import './style.scss';
import {
	AffirmIcon,
	AfterpayIcon,
	ClearpayIcon,
	AmericanExpressIcon,
	ApplePayIcon,
	CBIcon,
	IdealIcon,
	DiscoverIcon,
	GooglePayIcon,
	MastercardIcon,
	VisaIcon,
	WooIcon,
	KlarnaIcon,
} from 'wcpay/payment-methods-icons';

const PaymentMethods: React.FC = () => {
	return (
		<>
			<div className="connect-account-page__payment-methods__icons">
				<VisaIcon />
				<MastercardIcon />
				<AmericanExpressIcon />
				<CBIcon />
				<DiscoverIcon />
				<IdealIcon />
				<ApplePayIcon />
				<GooglePayIcon />
				<WooIcon />
				<KlarnaIcon />
				<AffirmIcon />
				{ 'GB' === wcpaySettings?.connect?.country ? (
					<ClearpayIcon />
				) : (
					<AfterpayIcon />
				) }
				<span>& more</span>
			</div>
		</>
	);
};

export default PaymentMethods;
