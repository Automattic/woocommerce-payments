/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

declare module 'components/paragraphs' {
	type ParagraphsParams = {
		children?: string[];
	};

	const Paragraphs: ( props: ParagraphsParams ) => JSX.Element;

	export = Paragraphs;
}
