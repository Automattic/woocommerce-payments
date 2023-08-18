/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import { HoverTooltip } from 'wcpay/components/tooltip';

export type ChipType = 'primary' | 'success' | 'light' | 'warning' | 'alert';

interface Props {
	message: string;
	type?: ChipType;
	className?: string;
	tooltip?: string;
}
const Chip: React.FC< Props > = ( {
	message,
	type = 'primary',
	className,
	tooltip,
} ) => {
	const classNames = [ 'chip', `chip-${ type }`, className ?? '' ];

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
