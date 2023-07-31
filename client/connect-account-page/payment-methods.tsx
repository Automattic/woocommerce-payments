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
import GooglePay from 'assets/images/cards/google-pay.svg?asset';
import JCB from 'assets/images/cards/jcb.svg?asset';
import MasterCard from 'assets/images/cards/mastercard.svg?asset';
import Sofort from 'assets/images/payment-methods/sofort.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import Visa from 'assets/images/cards/visa.svg?asset';
import WooPay from 'assets/images/payment-methods/woopay.svg?asset';
import Affirm from 'assets/images/payment-methods/affirm.svg?asset';
import AfterPay from 'assets/images/payment-methods/afterpay.svg?asset';
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
				{ wcpaySettings.isWooPayEligible && (
					<img src={ WooPay } alt="WooPay" />
				) }
				<img src={ ApplePay } alt="Apple Pay" />
				<img src={ GooglePay } alt="Google Pay" />
				<img src={ DinersClub } alt="DinersClub" />
				<img src={ UnionPay } alt="UnionPay" />
				<img src={ JCB } alt="JCB" />
				<img src={ Sofort } alt="Sofort" />
				<img src={ Affirm } alt="Affirm" />
				<img src={ AfterPay } alt="AfterPay" />
				<span>& more.</span>
			</div>
		</>
	);
};

export default PaymentMethods;
