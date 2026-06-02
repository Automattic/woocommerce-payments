/** @format **/

/**
 * External dependencies
 */
import { createElement, lazy, Suspense } from '@wordpress/element';

const UpdateBusinessDetailsTaskDescription = lazy( () =>
	import( './update-business-details-task-description' )
);

export const LazyUpdateBusinessDetailsTaskDescription = ( props ) =>
	createElement(
		Suspense,
		{
			fallback: props.updateByDescription
				? `${ props.error.reason } ${ props.updateByDescription }`
				: props.error.reason,
		},
		createElement( UpdateBusinessDetailsTaskDescription, props )
	);
