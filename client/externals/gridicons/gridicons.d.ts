/**
 * External dependencies
 */

declare module 'gridicons' {
	type GridiconParams = {
		icon: string;
		size: number;
	};
	const Gridicon: ( props: GridiconParams ) => JSX.Element;
	export = Gridicon;
}
