declare module 'gridicons/dist/*' {
	type GridiconParams = {
		size?: number;
		className?: string;
	};
	const Gridicon: ( props: GridiconParams ) => JSX.Element;

	export = Gridicon;
}
