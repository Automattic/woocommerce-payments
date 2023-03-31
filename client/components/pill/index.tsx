/**
 * External dependencies
 */
import React, { FC, ReactNode } from 'react';
import { Pill as WC_Pill } from '@woocommerce/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

type PillProps = {
	type?: 'primary' | 'success' | 'alert' | 'danger' | 'light';
	className?: string;
	children?: ReactNode;
};

const Pill: FC< PillProps > = ( { type = '', className = '', children } ) => {
	const types = [ 'primary', 'success', 'alert', 'danger', 'light' ];

	const classes = classNames(
		`wcpay-pill${ types.includes( type ) ? '__' + type : '' }`,
		className
	);

	return (
		<span className={ classes }>
			<WC_Pill>{ children }</WC_Pill>
		</span>
	);
};

export default Pill;
