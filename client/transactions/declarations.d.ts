// eslint-disable-next-line wpcalypso/import-docblock
import React from 'react';

declare module '@woocommerce/explat' {
	import * as Explat from '@woocommerce/explat';

	export interface Experiment {
		name: string;
		defaultExperience: React.ReactNode;
		treatmentExperience?: React.ReactNode;
		loadingExperience?: React.ReactNode;
	}
}
