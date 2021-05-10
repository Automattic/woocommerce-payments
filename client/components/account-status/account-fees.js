/** @format */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { getCurrency, formatCurrencyName } from 'utils/currency';
import { formatAccountFeesDescription } from 'utils/account-fees';

const AccountFees = ( props ) => {
	const { accountFees } = props;
	const baseFee = accountFees.base;

	const currency = getCurrency( baseFee.currency );
	const currencyName = formatCurrencyName( baseFee.currency );
	const currencyCode = currency?.getCurrencyConfig()?.code;
	const feeDescription = formatAccountFeesDescription( accountFees );

	return (
		<>
			{ currencyName ? `${ currencyName } ` : null }
			{ currencyCode ? `(${ currencyCode }) ` : null }
			{ feeDescription }
		</>
	);
};

export default AccountFees;
