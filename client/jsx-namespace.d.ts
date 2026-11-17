/**
 * External dependencies
 */
import type { JSX as ReactJSX } from 'react';

// React 19 types dropped the global `JSX` namespace in favour of `React.JSX`.
// Aliasing it here keeps the existing `JSX.Element` annotations compiling.
declare global {
	// eslint-disable-next-line @typescript-eslint/no-namespace
	namespace JSX {
		type Element = ReactJSX.Element;
		type IntrinsicElements = ReactJSX.IntrinsicElements;
	}
}
