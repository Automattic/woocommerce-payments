/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import AmericanExpress from 'assets/images/cards/amex.svg?asset';
import ApplePay from 'assets/images/cards/apple-pay.svg?asset';
import DinersClub from 'assets/images/cards/diners.svg?asset';
import Discover from 'assets/images/cards/discover.svg?asset';
import GiroPay from 'assets/images/payment-methods/giropay.svg?asset';
import GooglePay from 'assets/images/cards/google-pay.svg?asset';
import JCB from 'assets/images/cards/jcb.svg?asset';
import MasterCard from 'assets/images/cards/mastercard.svg?asset';
import Sofort from 'assets/images/payment-methods/sofort.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import Visa from 'assets/images/cards/visa.svg?asset';
import strings from './strings';
import './style.scss';

const PaymentMethods: React.FC = () => {
	return (
		<>
			<p>{ strings.paymentMethods }</p>
			<div className="connect-account-page__payment-methods__icons">
				<img src={ Visa } alt="Visa" />
				<img src={ MasterCard } alt="MasterCard" />
				<img src={ AmericanExpress } alt="American Express" />
				<img src={ ApplePay } alt="Apple Pay" />
				<img src={ GooglePay } alt="Google Pay" />
				<img src={ GiroPay } alt="GiroPay" />
				<img src={ DinersClub } alt="DinersClub" />
				<img src={ Discover } alt="Discover" />
				<img src={ UnionPay } alt="UnionPay" />
				<img src={ JCB } alt="JCB" />
				<img src={ Sofort } alt="Sofort" />
				<span>& more.</span>
			</div>
		</>
	);
};

export default PaymentMethods;
