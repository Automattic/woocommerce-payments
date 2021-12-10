/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

declare module 'components/loadable' {
	type LoadableBlockParams = {
		numLines?: number;
		isLoading: boolean;
		children?: React.ReactNode;
		display?: string;
		placeholder?: string;
		value?: string;
	};

	const LoadableBlock: ( props: LoadableBlockParams ) => JSX.Element;

	export = LoadableBlock;
}
