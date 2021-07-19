/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Tippy from '@tippyjs/react';
import './styles.scss';

const Tooltip = ( { content, children } ) => {
	return <Tippy content={ content }>{ children }</Tippy>;
};

export default Tooltip;
