/** @format */
/**
 * External dependencies
 */
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import './styles.scss';

const CurrencyDeleteIllustration = ( { symbol } ) => {
	return (
		<div className="currency-delete-illustration__wrapper">
			<div className="currency-delete-illustration__illustrations">
				{ symbol }
				<Gridicon
					icon="cross-circle"
					className="currency-delete-illustration__currency-cross-icon"
				/>
			</div>
		</div>
	);
};

export default CurrencyDeleteIllustration;
