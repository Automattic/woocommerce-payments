declare module '*?asset' {
	const src: string;
	export default src;
}

interface PaymentMethodsMapItem {
	icon: string;
	label: string;
}

type ReactImgFuncComponent = React.FunctionComponent<
	React.ImgHTMLAttributes< HTMLImageElement >
>;
