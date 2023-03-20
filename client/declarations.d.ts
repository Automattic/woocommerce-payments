declare module 'gridicons/dist/*' {
	type GridiconParams = {
		size?: number;
		className?: string;
	};
	const Gridicon: ( props: GridiconParams ) => JSX.Element;

	export = Gridicon;
}

declare module '*.svg';

/**
 * URL for an existing file in /assets folder
 */
declare module '*?asset' {
	const src: string;
	export default src;
}

type ReactImgFuncComponent = React.FunctionComponent<
	React.ImgHTMLAttributes< HTMLImageElement >
>;
