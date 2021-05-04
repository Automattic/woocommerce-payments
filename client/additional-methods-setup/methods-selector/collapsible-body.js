/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { useChildTaskContext } from '../setup-tasks/child-context';
import './collapsible-body.scss';

const CollapsibleBody = ( { children } ) => {
	const { isActive } = useChildTaskContext();

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
