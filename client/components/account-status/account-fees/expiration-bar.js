/** @format */

/**
 * Internal dependencies
 */
import ProgressBar from 'components/progress-bar';
import { formatCurrency } from 'utils/currency';

const ExpirationBar = ( {
	feeData: {
		volume_allowance: volumeAllowance,
		current_volume: currentVolume,
		...rest
	},
} ) => {
	if ( ! volumeAllowance ) {
		return null;
	}

	const currencyCode = rest.volume_currency ?? rest.currency;

	return (
		<ProgressBar
			progressLabel={ formatCurrency( currentVolume, currencyCode ) }
			totalLabel={ formatCurrency( volumeAllowance, currencyCode ) }
			progress={ currentVolume / volumeAllowance }
		/>
	);
};

export default ExpirationBar;
