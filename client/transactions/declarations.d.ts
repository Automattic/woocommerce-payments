declare module '@woocommerce/explat' {
	type ExperimentProps = {
		name: string;
		defaultExperience: JSX.Element;
		treatmentExperience?: JSX.Element;
		loadingExperience?: JSX.Element;
	};

	const Experiment: ( props: ExperimentProps ) => JSX.Element;
}

declare module 'interpolate-components' {
	type InterpolateComponentsProps = {
		mixedString: string;
		components: JSX.Element;
	};

	const interpolateComponents: (
		props: InterpolateComponentsProps
	) => JSX.Element;
}
