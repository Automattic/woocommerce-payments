/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { Icon, search } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';

const Search = ( props ) => {
	return (
		<div className="search">
			<Icon className="search__icon" icon={ search } />
			<input
				{ ...props }
				type="text"
				className="components-text-control__input"
			/>
		</div>
	);
};

export default Search;
