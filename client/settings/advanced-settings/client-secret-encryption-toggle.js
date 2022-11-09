/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useClientSecretEncryption } from 'wcpay/data';

const ClientSecretEncryptionToggle = () => {
	const [
		isClientSecretEncryptionEnabled,
		updateIsClientSecretEncryptionEnabled,
	] = useClientSecretEncryption();

	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! headingRef.current ) return;

		headingRef.current.focus();
	}, [] );

	const handleClientSecretEncryptionStatusChange = ( value ) => {
		updateIsClientSecretEncryptionEnabled( value );
	};

	return (
		<CheckboxControl
			label={ __(
				'Enable UPE Public Key Encryption',
				'woocommerce-payments'
			) }
			help={ __(
				'Encrypt the public keys used in the checkout form to harden card testing on your store.',
				'woocommerce-payments'
			) }
			checked={ isClientSecretEncryptionEnabled }
			onChange={ handleClientSecretEncryptionStatusChange }
		/>
	);
};

export default ClientSecretEncryptionToggle;
