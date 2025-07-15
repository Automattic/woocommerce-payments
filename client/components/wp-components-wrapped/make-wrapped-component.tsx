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
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__nextHasNoMarginBottom?: boolean;
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__next40pxDefaultSize?: boolean;
		}
	>( ( props, ref ) => {
		// extracting the `__nextHasNoMarginBottom` and `__next40pxDefaultSize` props from the list of props to avoid issues when running tests.
		// in the test environments, the `__nextHasNoMarginBottom` and `__next40pxDefaultSize` props generate a warning,
		// because we're using an outdated bundled version of WP components that doesn't recognize this prop.
		// to obviate this issue, let's extract it and provide it only when a context value is present
		// (which shouldn't be the case in test environments).

		// eslint-disable-next-line @typescript-eslint/naming-convention
		const {
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__nextHasNoMarginBottom,
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__next40pxDefaultSize,
			...rest
		} = props;
		const context = useContext( WordPressComponentsContext );

		if ( ! context ) {
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
				__next40pxDefaultSize={ __next40pxDefaultSize }
			/>
		);
	} );
