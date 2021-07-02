declare module '@woocommerce/explat' {
	type ExperimentProps = {
		name: string;
		defaultExperience: JSX.Element;
		treatmentExperience?: JSX.Element;
		loadingExperience?: JSX.Element;
	};

	const Experiment: ( props: ExperimentProps ) => JSX.Element;
}
