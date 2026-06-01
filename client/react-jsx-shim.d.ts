/**
 * React 19's `@types/react` removed the global `JSX` namespace in favour of
 * `React.JSX`. This project uses the classic JSX runtime (`jsx: "react"`),
 * whose type lookups still reference the global `JSX` namespace, and existing
 * code annotates return types as `JSX.Element`. Restore the global namespace
 * as an alias of `React.JSX` so those annotations keep resolving.
 */
import type * as React from 'react';

declare global {
	namespace JSX {
		type ElementType = React.JSX.ElementType;
		type Element = React.JSX.Element;
		type ElementClass = React.JSX.ElementClass;
		type ElementAttributesProperty = React.JSX.ElementAttributesProperty;
		type ElementChildrenAttribute = React.JSX.ElementChildrenAttribute;
		type LibraryManagedAttributes< C, P > =
			React.JSX.LibraryManagedAttributes< C, P >;
		type IntrinsicAttributes = React.JSX.IntrinsicAttributes;
		type IntrinsicClassAttributes< T > =
			React.JSX.IntrinsicClassAttributes< T >;
		type IntrinsicElements = React.JSX.IntrinsicElements;
	}
}
