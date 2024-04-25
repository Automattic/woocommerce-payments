/** @format */
/**
 * External dependencies
 */
import CrossCircleIcon from 'gridicons/dist/cross-circle';

/**
 * Internal dependencies
 */
import './styles.scss';

const CurrencyDeleteIllustration = ( { symbol } ) => {
	return (
		<div className="currency-delete-illustration__wrapper">
			<div className="currency-delete-illustration__illustrations">
				{ symbol }
				<CrossCircleIcon className="currency-delete-illustration__currency-cross-icon" />
			</div>
		</div>
	);
};

export default CurrencyDeleteIllustration;
