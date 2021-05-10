/**
 * External dependencies
 */
import React, { useLayoutEffect, useRef, useContext } from 'react';

/**
 * Internal dependencies
 */
import WizardContext from './wrapper/context';
import './parent-list.scss';

const ParentList = ( { children } ) => {
	const isFirstMount = useRef( true );
	const wrapperRef = useRef( null );
	const { activeTask } = useContext( WizardContext );

	useLayoutEffect( () => {
		// set the focus on the next active heading.
		// but need to set the focus only after the first mount, only when the active task changes.
		if ( true === isFirstMount.current ) {
			isFirstMount.current = false;
			return;
		}

		if ( ! wrapperRef.current ) {
			return;
		}

		const nextActiveTitle = wrapperRef.current.querySelector(
			'.wcpay-wizard__task.is-active .wcpay-wizard__task__headline'
		);
		if ( ! nextActiveTitle ) {
			return;
		}

		nextActiveTitle.focus();
	}, [ activeTask ] );

	return (
		<div className="wcpay-wizard__parent-list" ref={ wrapperRef }>
			<ul>{ children }</ul>
		</div>
	);
};

export default ParentList;
