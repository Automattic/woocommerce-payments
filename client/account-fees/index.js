/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import ProgressBar from 'components/progress-bar';
import { formatCurrency } from 'utils/currency';

const ExpirationBar = ( {
	feeData: {
		volume_allowance: volumeAllowance,
		current_volume: currentVolume,
	},
} ) => {
	if ( ! volumeAllowance ) {
		return null;
	}
	return (
		<ProgressBar
			progressLabel={ formatCurrency( currentVolume ) }
			totalLabel={ formatCurrency( volumeAllowance ) }
			progress={ currentVolume / volumeAllowance }
		/>
	);
};

const ExpirationDescription = ( {
	feeData: { volume_allowance: volumeAllowance, end_time: endTime },
} ) => {
	let description;
	if ( volumeAllowance && endTime ) {
		description = sprintf(
			/* translators: %1: total payment volume until this promotion expires %2: End date of the promotion */
			__(
				'Discounted base fee expires after the first %1$s of total payment volume or on %2$s.',
				'woocommerce-payments'
			),
			formatCurrency( volumeAllowance ),
			dateI18n( 'F j, Y', moment( endTime ).toISOString() )
		);
	} else if ( volumeAllowance ) {
		description = sprintf(
			/* translators: %1: total payment volume until this promotion expires */
			__(
				'Discounted base fee expires after the first %1$s of total payment volume.',
				'woocommerce-payments'
			),
			formatCurrency( volumeAllowance )
		);
	} else if ( endTime ) {
		description = sprintf(
			/* translators: %1: End date of the promotion */
			__(
				'Discounted base fee expires on %1$s.',
				'woocommerce-payments'
			),
			dateI18n( 'F j, Y', moment( endTime ).toISOString() )
		);
	} else {
		return null;
	}
	return <p className="description">{ description }</p>;
};

const AccountFees = ( { accountFees } ) => {
	const baseFee = accountFees.base;
	let currentFee = baseFee;

	let feeDescription = sprintf(
		/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
		__( '%1$.1f%% + %2$s per transaction', 'woocommerce-payments' ),
		currentFee.percentage_rate * 100,
		formatCurrency( currentFee.fixed_rate, baseFee.currency )
	);

	if ( accountFees.discount.length ) {
		// TODO: Figure out how the UI should work if there are several "discount" fees stacked.
		currentFee = accountFees.discount[ 0 ];
		let percentage, fixed;

		if ( currentFee.discount ) {
			// Proper discount fee (XX% off)
			percentage = baseFee.percentage_rate * ( 1 - currentFee.discount );
			fixed = baseFee.fixed_rate * ( 1 - currentFee.discount );
		} else {
			// Custom base fee (2% + $.20)
			percentage = currentFee.percentage_rate;
			fixed = currentFee.fixed_rate;
		}

		let descriptionString = sprintf(
			/* translators: %1 Base fee (that don't apply to this account at this moment), %2 and %3: Current fee (e.g: 2.9% + $.30) */
			__(
				'<s>%1$s</s> %2$.1f%% + %3$s per transaction',
				'woocommerce-payments'
			),
			feeDescription,
			percentage * 100,
			formatCurrency( fixed, baseFee.currency )
		);

		if ( currentFee.discount ) {
			descriptionString +=
				' ' +
				sprintf(
					/* translators: %d percentage discount to apply */
					__( '(%1$d%% discount)', 'woocommerce-payments' ),
					currentFee.discount * 100
				);
		}

		feeDescription = createInterpolateElement( descriptionString, {
			s: <s />,
		} );
	}

	return (
		<>
			<p>{ feeDescription }</p>
			<ExpirationBar feeData={ currentFee } />
			<ExpirationDescription feeData={ currentFee } />
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
		</>
	);
};

export default AccountFees;
