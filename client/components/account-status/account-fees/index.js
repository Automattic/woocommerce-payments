/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ExpirationBar from './expiration-bar';
import ExpirationDescription from './expiration-description';
import {
	formatCurrencyName,
	getCurrency,
} from 'multi-currency/interface/functions';
import {
	formatAccountFeesDescription,
	getCurrentBaseFee,
	getTransactionsPaymentMethodName,
} from 'utils/account-fees';
import { CardDivider } from '@wordpress/components';
import './styles.scss';

const AccountFee = ( props ) => {
	const { accountFee, paymentMethod } = props;
	const baseFee = accountFee.base;
	const currency = getCurrency( baseFee.currency );
	const currencyName = formatCurrencyName( baseFee.currency );
	const currencyCode = currency?.getCurrencyConfig()?.code;
	const feeDescription = formatAccountFeesDescription( accountFee );
	const currentBaseFee = getCurrentBaseFee( accountFee );

	return (
		<>
			<p>{ getTransactionsPaymentMethodName( paymentMethod ) }:</p>
			{ currencyName ? `${ currencyName } ` : null }
			{ currencyCode ? `(${ currencyCode }) ` : null }
			{ feeDescription }
			<ExpirationBar feeData={ currentBaseFee } />
			<ExpirationDescription feeData={ currentBaseFee } />
		</>
	);
};

const AccountFees = ( props ) => {
	const { accountFees } = props;
	let haveDiscounts = false;
	const activeDiscounts = Object.entries( accountFees ).map(
		( [ key, value ] ) => {
			if ( value.fee.discount.length === 0 ) {
				return null;
			}
			haveDiscounts = true;
			return (
				<AccountFee
					key={ key }
					paymentMethod={ value.payment_method }
					accountFee={ value.fee }
				/>
			);
		}
	);
	return (
		<>
			{ haveDiscounts && (
				<div className="account-fees">
					<CardDivider />
					<h4>
						{ __( 'Active discounts', 'woocommerce-payments' ) }
					</h4>
				</div>
			) }
			{ activeDiscounts }
		</>
	);
};

export default AccountFees;
