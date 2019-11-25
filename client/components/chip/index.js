/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import './style.scss';

const Chip = ( props ) => {
	const { message, type } = props;
	const types = [ 'primary', 'light', 'alert' ];
	return (
		<span className={ `chip chip-${ types.find( t => t === type ) || 'primary' }` }>
			{ message }
		</span>
	);
};

export default Chip;
