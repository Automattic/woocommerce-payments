/** @format **/

/**
 * Internal dependencies
 */
import './style.scss';
import { HoverTooltip } from 'wcpay/components/tooltip';

const types = [ 'primary', 'success', 'light', 'warning', 'alert' ];

const Chip = ( props ) => {
	const { message, type, className, tooltip } = props;

	const classNames = [
		'chip',
		`chip-${ types.find( ( t ) => t === type ) || 'primary' }`,
		className ?? '',
	];

	if ( tooltip ) {
		return (
			<HoverTooltip content={ tooltip }>
				<span className={ classNames.join( ' ' ).trim() }>
					{ message }
				</span>
			</HoverTooltip>
		);
	}
	return <span className={ classNames.join( ' ' ).trim() }>{ message }</span>;
};

export default Chip;
