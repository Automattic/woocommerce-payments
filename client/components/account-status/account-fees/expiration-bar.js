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
	},
	currencyCode,
} ) => {
	if ( ! volumeAllowance ) {
		return null;
	}
	return (
		<ProgressBar
			progressLabel={ formatCurrency( currentVolume, currencyCode ) }
			totalLabel={ formatCurrency( volumeAllowance, currencyCode ) }
			progress={ currentVolume / volumeAllowance }
		/>
	);
};

export default ExpirationBar;
