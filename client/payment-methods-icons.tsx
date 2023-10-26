/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import BancontactAsset from 'assets/images/payment-methods/bancontact.svg?asset';
import EpsAsset from 'assets/images/payment-methods/eps.svg?asset';
import GiropayAsset from 'assets/images/payment-methods/giropay.svg?asset';
import SofortAsset from 'assets/images/payment-methods/sofort.svg?asset';
import SepaAsset from 'assets/images/payment-methods/sepa-debit.svg?asset';
import P24Asset from 'assets/images/payment-methods/p24.svg?asset';
import IdealAsset from 'assets/images/payment-methods/ideal.svg?asset';
import BankDebitAsset from 'assets/images/payment-methods/bank-debit.svg?asset';
import AffirmAsset from 'assets/images/payment-methods/affirm.svg?asset';
import AfterpayAsset from 'assets/images/payment-methods/afterpay.svg?asset';
import JCBAsset from 'assets/images/payment-methods/jcb.svg?asset';
import KlarnaAsset from 'assets/images/payment-methods/klarna.svg?asset';
import VisaAsset from 'assets/images/cards/visa.svg?asset';
import MasterCardAsset from 'assets/images/cards/mastercard.svg?asset';
import AmexAsset from 'assets/images/cards/amex.svg?asset';
import WooAsset from 'assets/images/payment-methods/woo.svg?asset';
import ApplePayAsset from 'assets/images/cards/apple-pay.svg?asset';
import GooglePayAsset from 'assets/images/cards/google-pay.svg?asset';
import DinersClubAsset from 'assets/images/cards/diners.svg?asset';
import UnionPayAsset from 'assets/images/cards/unionpay.svg?asset';

import './style.scss';

const iconComponent = ( src: string, alt: string ): ReactImgFuncComponent => (
	props
) => <img className={ 'payment-method__icon' } src={ src } alt={ alt } />;

export const AffirmIcon = iconComponent( AffirmAsset, 'Affirm' );
export const AfterpayIcon = iconComponent( AfterpayAsset, 'Afterpay' );
export const AmericanExpressIcon = iconComponent(
	AmexAsset,
	'American Express'
);
export const ApplePayIcon = iconComponent( ApplePayAsset, 'Apple Pay' );
export const BancontactIcon = iconComponent( BancontactAsset, 'Bancontact' );
export const BankDebitIcon = iconComponent(
	BankDebitAsset,
	'BECS Direct Debit'
);
export const DinersClubIcon = iconComponent( DinersClubAsset, 'Diners Club' );
export const EpsIcon = iconComponent( EpsAsset, 'BECS Direct Debit' );
export const GiropayIcon = iconComponent( GiropayAsset, 'Giropay' );
export const GooglePayIcon = iconComponent( GooglePayAsset, 'Google Pay' );
export const IdealIcon = iconComponent( IdealAsset, 'iDEAL' );
export const JCBIcon = iconComponent( JCBAsset, 'JCB' );
export const KlarnaIcon = iconComponent( KlarnaAsset, 'Klarna' );
export const MastercardIcon = iconComponent( MasterCardAsset, 'Mastercard' );
export const P24Icon = iconComponent( P24Asset, 'Przelewy24 (P24)' );
export const SepaIcon = iconComponent( SepaAsset, 'SEPA Direct Debit' );
export const SofortIcon = iconComponent( SofortAsset, 'Sofort' );
export const UnionPayIcon = iconComponent( UnionPayAsset, 'UnionPay' );
export const VisaIcon = iconComponent( VisaAsset, 'Visa' );
export const WooIcon = iconComponent( WooAsset, 'WooPay' );

export const CreditCardIcon: React.FC = () => {
	return (
		<div className="payment-method__grid">
			<VisaIcon />
			<MastercardIcon />
			<AmericanExpressIcon />
			<DinersClubIcon />
		</div>
	);
};
