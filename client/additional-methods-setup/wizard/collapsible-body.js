/**
 * External dependencies
 */
import React, { useContext } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import WizardTaskContext from './task/context';
import './collapsible-body.scss';

const CollapsibleBody = ( { children } ) => {
	const { isActive } = useContext( WizardTaskContext );

	return (
		<div
			className={ classNames( 'task-collapsible-body', {
				'is-active': isActive,
			} ) }
		>
			{ children }
		</div>
	);
};

export default CollapsibleBody;
