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
	tooltipPostion?: string;
	tooltipTheme?: string;
}
const Chip: React.FC< Props > = ( {
	message,
	type = 'primary',
	className,
	tooltip,
	tooltipPostion = 'center',
	tooltipTheme = 'black',
} ) => {
	const classNames = [ 'chip', `chip-${ type }`, className ?? '' ];

	if ( tooltip ) {
		return (
			<HoverTooltip
				content={ tooltip }
				position={ tooltipPostion }
				theme={ tooltipTheme }
			>
				<span className={ classNames.join( ' ' ).trim() }>
					{ message }
				</span>
			</HoverTooltip>
		);
	}
	return <span className={ classNames.join( ' ' ).trim() }>{ message }</span>;
};

export default Chip;
