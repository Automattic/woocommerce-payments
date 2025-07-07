/**
 * External dependencies
 */
import React, { ComponentProps, useContext } from 'react';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

export const makeWrappedComponent = <
	T extends React.ComponentType< any >,
	N extends string
>(
	BundledComponent: T,
	componentName: N
) =>
	React.forwardRef<
		any,
		ComponentProps< T > & { useBundledComponent?: boolean }
	>( ( props, ref ) => {
		const { useBundledComponent, ...rest } = props;
		const context = useContext( WordPressComponentsContext );

		if ( ! context || useBundledComponent ) {
			// @ts-expect-error: the type of props is not always well-defined, ignoring the error.
			return <BundledComponent { ...rest } ref={ ref } />;
		}

		const ContextComponent = context[
			componentName as keyof typeof context
		] as React.ComponentType< any >;

		return <ContextComponent { ...rest } ref={ ref } />;
	} );
