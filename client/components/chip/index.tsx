/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Icon } from '@wordpress/icons';
import clsx from 'clsx';

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
	icon?: React.JSX.Element;
}
const Chip: React.FC< React.PropsWithChildren< Props > > = ( {
	message,
	type = 'primary',
	className,
	tooltip,
	icon,
} ) => {
	const classNames = clsx(
		'chip',
		`chip-${ type }`,
		icon && 'chip-has-icon',
		className
	);

	const content = (
		<>
			{ icon && <Icon icon={ icon } size={ 16 } /> }
			{ message }
		</>
	);

	if ( tooltip ) {
		return (
			<HoverTooltip content={ tooltip }>
				<span className={ classNames }>{ content }</span>
			</HoverTooltip>
		);
	}
	return <span className={ classNames }>{ content }</span>;
};

export default Chip;
