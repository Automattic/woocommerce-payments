/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';

export type chipType = 'primary' | 'light' | 'warning' | 'alert';

const Chip = ( props: {
	message: string;
	type?: chipType;
	isCompact?: boolean;
} ): JSX.Element => {
	const { message, type = 'primary', isCompact } = props;

	const classNames = [
		'chip',
		`chip-${ type }`,
		isCompact ? 'is-compact' : '',
	];

	return <span className={ classNames.join( ' ' ).trim() }>{ message }</span>;
};

export default Chip;
