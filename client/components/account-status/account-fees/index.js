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
import { getCurrency, formatCurrencyName } from 'utils/currency';
import {
	formatAccountFeesDescription,
	getCurrentFee,
	getTransactionsPaymentMethodName,
} from 'utils/account-fees';

const LearnMoreLink = ( { accountFees } ) => {
	return (
		<p>
			<a
				href={
					accountFees.discount.length
						? 'https://woocommerce.com/terms-conditions/woocommerce-payments-promotion/'
						: 'https://docs.woocommerce.com/document/payments/faq/fees/'
				}
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Learn more', 'woocommerce-payments' ) }
			</a>
		</p>
	);
};

const AccountFee = ( props ) => {
	const { accountFee, paymentMethod } = props;
	const baseFee = accountFee.base;
	const currency = getCurrency( baseFee.currency );
	const currencyName = formatCurrencyName( baseFee.currency );
	const currencyCode = currency?.getCurrencyConfig()?.code;
	const feeDescription = formatAccountFeesDescription( accountFee );
	const currentFee = getCurrentFee( accountFee );

	return (
		<>
			<p>{ getTransactionsPaymentMethodName( paymentMethod ) }:</p>
			{ currencyName ? `${ currencyName } ` : null }
			{ currencyCode ? `(${ currencyCode }) ` : null }
			{ feeDescription }
			<ExpirationBar feeData={ currentFee } />
			<ExpirationDescription feeData={ currentFee } />
			<LearnMoreLink accountFees={ accountFee } />
		</>
	);
};

const AccountFees = ( props ) => {
	const { accountFees } = props;

	return (
		<>
			{ Object.entries( accountFees ).map( ( [ key, value ] ) => {
				//ignore base and discount fields - still used for backwards compatibilityss
				if (
					'base' === key ||
					'discount' === key ||
					0 === value.discount.length
				) {
					return null;
				}

				return (
					<AccountFee
						key={ key }
						paymentMethod={ key }
						accountFee={ value }
					/>
				);
			} ) }
		</>
	);
};

export default AccountFees;
