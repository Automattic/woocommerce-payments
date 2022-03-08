declare module 'wcpay/settings/settings-section' {
	interface SettingsSectionProps {
		/* eslint-disable-next-line @typescript-eslint/naming-convention */
		Description: () => JSX.Element;
		children: React.ReactNode;
	}

	const SettingsSection: ( props: SettingsSectionProps ) => JSX.Element;

	export = SettingsSection;
}
