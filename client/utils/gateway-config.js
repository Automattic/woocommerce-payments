export function getMainGatewayId() {
	return window.wcpayGatewayConfig?.mainGatewayId || 'woocommerce_payments';
}

export function getMainPaymentMethodId() {
	return window.wcpayGatewayConfig?.mainPaymentMethodId || 'main';
}

export function getCardGatewayId() {
	return (
		window.wcpayGatewayConfig?.cardGatewayId || 'woocommerce_payments_card'
	);
}

export function getCardPaymentMethodId() {
	return window.wcpayGatewayConfig?.cardPaymentMethodId || 'card';
}

export function isUsingSeparateGateways() {
	return window.wcpayGatewayConfig?.isUsingSeparateGateways || false;
}
