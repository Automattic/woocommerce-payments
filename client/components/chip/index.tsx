/** @format **/
import React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import { HoverTooltip } from 'wcpay/components/tooltip';

const types = [ 'primary', 'light', 'warning', 'alert' ];

export type ChipType = typeof types[ number ];

interface Props {
	message: string;
	type: ChipType;
	isCompact?: boolean;
	className?: string;
	tooltip?: React.ReactNode;
}

const Chip: React.FC< Props > = ( props ) => {
	const { message, type, isCompact, className, tooltip } = props;

	const classNames = [
		'chip',
		`chip-${ types.find( ( t ) => t === type ) || 'primary' }`,
		isCompact ? 'is-compact' : '',
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
