/** @format **/

/**
 * External dependencies
 */
import Gridicon from 'gridicons';

/**
 * Internal dependencies.
 */
import './style.scss';

/* TODO: find a default Chip component */
/* TODO: implement other types/statuses */
const Chip = ( props ) => {
	const { message, type, style } = props;
	const icon = 'warning' === type ? 'notice' : 'checkmark';
	const fill = 'warning' === type ? '#302800' : '#162566';
	const backgroundColor = 'warning' === type ? '#F7E1AE' : '#DBE8FF';
	return (
		<span className="chip" style={ { backgroundColor, color: fill, ...style } }>
			<Gridicon icon={ icon } size={ 18 } style={ { fill, verticalAlign: 'text-top', marginRight: '4px' } } />
			{ message }
		</span>
	);
};

export default Chip;
