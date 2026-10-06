// Script modules can't import @wordpress packages, so the fields' JavaScript parts come from the settings screen script.
export default window.wcpaySettingsScreen?.fieldParts ?? {};
