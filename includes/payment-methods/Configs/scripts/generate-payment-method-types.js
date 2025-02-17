/**
 * Generate Payment Method Types
 *
 * This script reads the JSON definitions and generates TypeScript
 * types and constants for use in the frontend code.
 */

const { readFileSync, writeFileSync, mkdirSync, existsSync } = require( 'fs' );
const { resolve } = require( 'path' );

// Read the JSON definitions
const definitionsPath = resolve(
	__dirname,
	'../../../../build/payment-methods/definitions.json'
);
const definitions = JSON.parse( readFileSync( definitionsPath, 'utf8' ) );

// Create the client directory if it doesn't exist
const clientDir = resolve( __dirname, '../../../../client/payment-methods' );
if ( ! existsSync( clientDir ) ) {
	mkdirSync( clientDir, { recursive: true } );
}

// Generate the TypeScript types
const typesContent = `/**
 * This file is auto-generated. Do not edit manually.
 */

export interface PaymentMethodIcon {
	path: string;
	dark_path?: string;
}

export interface PaymentMethodIcons {
	default: PaymentMethodIcon;
	dark?: PaymentMethodIcon;
}

export interface PaymentMethodDefinition {
	id: string;
	stripeId: string;
	title: string;
	description: string;
	capabilities: string[];
	currencies: string[];
	countries: string[];
	allowsManualCapture: boolean;
	allowsPayLater: boolean;
	acceptsOnlyDomesticPayment: boolean;
	settingsIcon: string;
	icons: PaymentMethodIcons;
}

export type PaymentMethodConfigurations = {
	[ key: string ]: PaymentMethodDefinition;
};

export const PaymentMethodCapability = {
${ Object.entries( definitions.capabilities )
	.map( ( [ key, value ] ) => `\t${ key }: '${ value }' as const,` )
	.join( '\n' ) }
} as const;

export type PaymentMethodCapabilityType = typeof PaymentMethodCapability[keyof typeof PaymentMethodCapability];

export const PaymentMethodDefinitions: PaymentMethodConfigurations = ${ JSON.stringify(
	definitions.paymentMethods,
	null,
	'\t'
) };
`;

// Write the TypeScript file with trailing newline
writeFileSync( resolve( clientDir, 'types.ts' ), typesContent + '\n' );
