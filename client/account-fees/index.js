/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import Currency from "@woocommerce/currency";
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import ProgressBar from 'components/progress-bar';

const currency = new Currency();

const ExpirationBar = ( { feeData } ) => {
	const { volume_allowance: volumeAllowance, current_volume: currentVolume } = feeData;
	if ( ! volumeAllowance ) {
		return null;
	}
	return (
		<>
			<ProgressBar
				progressLabel={ currency.formatCurrency( currentVolume / 100 ) }
				totalLabel={ currency.formatCurrency( volumeAllowance / 100 ) }
				progress={ currentVolume / volumeAllowance }
			/>
			<p className="description">
				{
					sprintf(
						/* translators: %1: the authorized amount, %2: transaction ID of the payment */
						__('Discounted base fee expires after the first %1$s of total payment volume.', 'woocommerce-payments'),
						currency.formatCurrency( volumeAllowance / 100 )
					)
				}
			</p>
		</>
	);
}

const AccountFees = ( { accountFees } ) => {
	let currentFee = accountFees.base;

	let feeDescription = sprintf(
		__( '%1$.1f%% + %2$s per transaction', 'woocommerce-payments' ),
		currentFee.percentage_rate * 100,
		currency.formatCurrency( currentFee.fixed_rate / 100 )
	);

	if ( accountFees.discount.length ) {
		// TODO: Figure out how the UI should work if there are several "discount" fees stacked.
		currentFee = accountFees.discount[0];

		const percentage = accountFees.base.percentage_rate * 100 * ( 1 - currentFee.discount );
		const fixed = accountFees.base.fixed_rate / 100 * ( 1 - currentFee.discount );
		feeDescription = createInterpolateElement(
			sprintf(
				/* translators: %s - formatted requirements current deadline, <a> - dashboard login URL */
				__( '<s>%1$s</s> %2$.1f%% + %3$s per transaction (%4$d%% discount)', 'woocommerce-payments' ),
				feeDescription,
				percentage,
				currency.formatCurrency( fixed ),
				currentFee.discount * 100,
			),
			{ s: <s /> }
		);
	}

	return (
		<>
			<p>{ feeDescription }</p>
			<ExpirationBar feeData={ currentFee } />
			<p>
				<a href="https://docs.woocommerce.com/document/payments/faq/fees/" target="_blank" rel="noopener noreferrer">
					{ __( 'Learn more', 'woocommerce-payments' ) }
				</a>
			</p>
		</>
	);
};

export default AccountFees;
