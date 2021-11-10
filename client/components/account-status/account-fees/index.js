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
} from 'utils/account-fees';

const LearnMoreLink = ( { accountFees } ) => {
	return (
		<p>
			<a
				href={
					accountFees.discount.length
						? 'https://woocommerce.com/terms-conditions/woocommerce-payments-promotion/'
						: 'https://woocommerce.com/document/payments/faq/fees/'
				}
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Learn more', 'woocommerce-payments' ) }
			</a>
		</p>
	);
};

const AccountFees = ( props ) => {
	const { accountFees } = props;
	const baseFee = accountFees.base;

	const currency = getCurrency( baseFee.currency );
	const currencyName = formatCurrencyName( baseFee.currency );
	const currencyCode = currency?.getCurrencyConfig()?.code;
	const feeDescription = formatAccountFeesDescription( accountFees );
	const currentFee = getCurrentFee( accountFees );

	return (
		<>
			{ currencyName ? `${ currencyName } ` : null }
			{ currencyCode ? `(${ currencyCode }) ` : null }
			{ feeDescription }
			<ExpirationBar feeData={ currentFee } />
			<ExpirationDescription feeData={ currentFee } />
			<LearnMoreLink accountFees={ accountFees } />
		</>
	);
};

export default AccountFees;
