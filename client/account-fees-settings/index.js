/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import ProgressBar from 'components/progress-bar';
import {
	formatAccountFeesDescription,
	getCurrentFee,
} from 'utils/account-fees';
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
	const currentFee = getCurrentFee( accountFees );
	const feeDescription = formatAccountFeesDescription( accountFees );

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
