declare module '*?asset' {
	const src: string;
	export default src;
}

type ReactImgFuncComponent = React.FunctionComponent<
	React.ImgHTMLAttributes< HTMLImageElement >
>;