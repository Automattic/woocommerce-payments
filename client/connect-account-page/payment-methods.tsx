/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

import strings from './strings';
import './style.scss';
import {
	AffirmIcon,
	AfterpayIcon,
	AmericanExpressIcon,
	ApplePayIcon,
	DinersClubIcon,
	GooglePayIcon,
	JCBIcon,
	MastercardIcon,
	UnionPayIcon,
	VisaIcon,
	WooIcon,
} from 'wcpay/payment-methods-icons';

const PaymentMethods: React.FC = () => {
	return (
		<>
			<p>{ strings.paymentMethods }</p>
			<div className="connect-account-page__payment-methods__icons">
				<VisaIcon />
				<MastercardIcon />
				<AmericanExpressIcon />
				{ wcpaySettings.isWooPayStoreCountryAvailable && <WooIcon /> }
				<ApplePayIcon />
				<GooglePayIcon />
				<DinersClubIcon />
				<UnionPayIcon />
				<JCBIcon />
				<AffirmIcon />
				<AfterpayIcon />
				<span>& more.</span>
			</div>
		</>
	);
};

export default PaymentMethods;
