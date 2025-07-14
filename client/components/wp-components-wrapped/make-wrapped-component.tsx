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
		ComponentProps< T > & {
			useBundledComponent?: boolean;
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__nextHasNoMarginBottom?: boolean;
		}
	>( ( props, ref ) => {
		// extracting the `__nextHasNoMarginBottom` from the list of props to avoid issues when running tests.
		// in the test environments, the `__nextHasNoMarginBottom` prop generates a warning,
		// because we're using an outdated bundled version of WP components that doesn't recognize this prop.
		// to obviate this issue, let's extract it and provide it only when a context value is present
		// (which shouldn't be the case in test environments).

		// eslint-disable-next-line @typescript-eslint/naming-convention
		const { useBundledComponent, __nextHasNoMarginBottom, ...rest } = props;
		const context = useContext( WordPressComponentsContext );

		if ( ! context || useBundledComponent ) {
			// @ts-expect-error: the type of props is not always well-defined, ignoring the error.
			return <BundledComponent { ...rest } ref={ ref } />;
		}

		const ContextComponent = context[
			componentName as keyof typeof context
		] as React.ComponentType< any >;

		return (
			<ContextComponent
				{ ...rest }
				ref={ ref }
				__nextHasNoMarginBottom={ __nextHasNoMarginBottom }
			/>
		);
	} );
