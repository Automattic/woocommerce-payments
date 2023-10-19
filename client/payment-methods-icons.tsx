/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import CreditCardAsset from 'assets/images/payment-methods/cc.svg?asset';
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
import CartesBancairesAsset from 'assets/images/cards/diners.svg?asset';
import './style.scss';

const iconComponent = ( src: string, alt: string ): ReactImgFuncComponent => (
	props
) => (
	<div className={ 'payment-method__icon_x' }>
		<img src={ src } alt={ alt } { ...props } />
	</div>
);

export const CreditCardIcon: React.FC = () => {
	return (
		<div className="payment-method__grid">
			<div className="payment-method__icon">
				{ /* eslint-disable-next-line jsx-a11y/alt-text */ }
				<img src={ VisaAsset } />
			</div>
			<div className="express-checkout__icon">
				{ /* eslint-disable-next-line jsx-a11y/alt-text */ }
				<img src={ MasterCardAsset } />
			</div>
			<div className="express-checkout__icon">
				{ /* eslint-disable-next-line jsx-a11y/alt-text */ }
				<img src={ AmexAsset } />
			</div>
			<div className="express-checkout__icon">
				{ /* eslint-disable-next-line jsx-a11y/alt-text */ }
				<img src={ CartesBancairesAsset } />
			</div>
		</div>
	);
};

export const BancontactIcon = iconComponent( BancontactAsset, 'Bancontact' );
export const EpsIcon = iconComponent( EpsAsset, 'BECS Direct Debit' );
export const GiropayIcon = iconComponent( GiropayAsset, 'Giropay' );
export const SofortIcon = iconComponent( SofortAsset, 'Sofort' );
export const SepaIcon = iconComponent( SepaAsset, 'SEPA Direct Debit' );
export const P24Icon = iconComponent( P24Asset, 'Przelewy24 (P24)' );
export const IdealIcon = iconComponent( IdealAsset, 'iDEAL' );
export const BankDebitIcon = iconComponent(
	BankDebitAsset,
	'BECS Direct Debit'
);
export const AffirmIcon = iconComponent( AffirmAsset, 'Affirm' );
export const AfterpayIcon = iconComponent( AfterpayAsset, 'Afterpay' );
export const JCBIcon = iconComponent( JCBAsset, 'JCB' );
export const KlarnaIcon = iconComponent( KlarnaAsset, 'Klarna' );
