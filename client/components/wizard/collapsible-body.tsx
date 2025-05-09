/**
 * External dependencies
 */
import React, { useContext } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import WizardTaskContext from './task/context';
import './collapsible-body.scss';

const CollapsibleBody: React.FC< React.HTMLAttributes< HTMLDivElement > > = ( {
	className,
	...restProps
} ) => {
	const { isActive } = useContext( WizardTaskContext );

	return (
		<div
			className={ clsx( 'task-collapsible-body', className, {
				'is-active': isActive,
			} ) }
			{ ...restProps }
		/>
	);
};

export default CollapsibleBody;
