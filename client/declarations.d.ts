declare module 'gridicons/dist/*' {
	type GridiconParams = {
		size?: number;
		className?: string;
	};
	const Gridicon: ( props: GridiconParams ) => JSX.Element;

	export = Gridicon;
}

type ReactImgComponent = React.FunctionComponent<
	React.ImgHTMLAttributes< HTMLImageElement >
>;

declare module '*.svg' {
	const img: ReactImgComponent;
	export default img;
}

declare module '*.png' {
	const img: ReactImgComponent;
	export default img;
}
