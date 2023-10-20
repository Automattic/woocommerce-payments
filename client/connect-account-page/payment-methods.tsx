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
				<VisaIcon className={ 'small' } />
				<MastercardIcon className={ 'small' } />
				<AmericanExpressIcon className={ 'small' } />
				{ wcpaySettings.isWooPayStoreCountryAvailable && (
					<WooIcon className={ 'small' } />
				) }
				<ApplePayIcon className={ 'small' } />
				<GooglePayIcon className={ 'small' } />
				<DinersClubIcon className={ 'small' } />
				<UnionPayIcon className={ 'small' } />
				<JCBIcon className={ 'small' } />
				<AffirmIcon className={ 'small' } />
				<AfterpayIcon className={ 'small' } />
				<span>& more.</span>
			</div>
		</>
	);
};

export default PaymentMethods;
