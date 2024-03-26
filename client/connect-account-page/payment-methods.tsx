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
	DinersClubIcon,
	DiscoverIcon,
	GooglePayIcon,
	MastercardIcon,
	SofortIcon,
	VisaIcon,
	WooIcon,
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
				<DinersClubIcon />
				<ApplePayIcon />
				<GooglePayIcon />
				{ wcpaySettings.isWooPayStoreCountryAvailable && <WooIcon /> }
				<WooIcon />
				<SofortIcon />
				<AffirmIcon />
				{ 'GB' === wcpaySettings?.connect?.country ? (
					<ClearpayIcon />
				) : (
					<AfterpayIcon />
				) }
				<span>& more.</span>
			</div>
		</>
	);
};

export default PaymentMethods;
