// import path from "path";
// @ts-nocheck
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import tsconfigPaths from 'vite-tsconfig-paths';
import externalGlobals from 'rollup-plugin-external-globals';

import cacheBusting from './vite/cache-busting';
import {
	assetize,
	externalize,
	globalize,
	list,
} from './vite/dependency-extraction';

// Try to restore file path
const grabAsset = () => ( {
	name: 'grabAsset',
	generateBundle( options, bundle ) {
		Object.entries( bundle ).forEach( ( [ fileName, fileInfo ] ) => {
			// Skips files that aren’t CSS or are asset types
			if ( ! fileName.includes( '.css' ) ) return;

			// `firstImport` should be the key in the bundle to the
			// corresponding asset file which Vite has generated.
			const [ firstImport ] = fileInfo?.viteMetadata?.importedCss ?? [];
			console.debug( '\n' );
			console.debug( fileInfo );
			if ( firstImport && firstImport in bundle )
				bundle[ firstImport ].fileName = fileName.replace(
					/\.js$/,
					''
				);
		} );
	},
} );

// https://vitejs.dev/config/
export default defineConfig( {
	plugins: [
		react(),
		tsconfigPaths(),
		// assetize(),
		cacheBusting(),
		// externalGlobals(globalize, {
		// 	//   exclude: ["node_modules/**/*"],
		// 	include: ['client/**/*'],
		// 	exclude: ['**/*.scss'],
		// }),
		// externalizeDeps({
		// 	deps: true,
		// 	devDeps: false,
		// 	// except: /@woocommerce/
		// })
	],
	// assetsInclude: ['./assets/**/*.svg', 'assets/images/payment-methods/sofort.svg'],
	// publicDir: "./assets", // Include assets in ./dist
	build: {
		chunkSizeWarningLimit: 16000,
		// cssCodeSplit: true,
		// lib: {
		//   name: "wcpay",
		//   // formats: ["iife"],
		//   entry: {
		//     index: "./client/index.js",
		//     order: "./client/order/index.jsx",
		//   },
		// },
		rollupOptions: {
			input: {
				index: "./client/index.js",
				// settings: "./client/settings/index.jsx",
				// "blocks-checkout": "./client/checkout/blocks/index.jsx",
				// woopay: "./client/checkout/woopay/index.jsx",
				// "woopay-express-button":
				//   "./client/checkout/woopay/express-button/index.jsx",
				// "woopay-direct-checkout":
				//   "./client/checkout/woopay/direct-checkout/index.js",
				// checkout: "./client/checkout/classic/event-handlers.js",
				// "payment-request": "./client/payment-request/index.js",
				// "subscription-edit-page": "./client/subscription-edit-page.js",
				// tos: './client/tos/index.jsx',
				// "payment-gateways": "./client/payment-gateways/index.jsx",
				// "multi-currency": "./client/multi-currency/index.jsx",
				// "multi-currency-switcher-block":
				//   "./client/multi-currency/blocks/currency-switcher.jsx",
				// "multi-currency-analytics":
				//   "./client/multi-currency-analytics/index.js",
				// order: "./client/order/index.jsx",
				// "subscriptions-empty-state":
				//   "./client/subscriptions-empty-state/index.jsx",
				// "subscription-product-onboarding-modal":
				//   "./client/subscription-product-onboarding/modal.jsx",
				// "subscription-product-onboarding-toast":
				//   "./client/subscription-product-onboarding/toast.js",
				// 'product-details': './client/product-details/index.js',
			},
			output: {
				globals: globalize,
				entryFileNames: '[name].js',
				chunkFileNames: '[name].js',
				assetFileNames: ( chunkInfo ) => {
					if ( chunkInfo.name?.includes( '.css' ) ) {
						return `[name][extname]`;
					}
					return `assets/[name][extname]`;
				},
				// manualChunks: () => "_______.js",
				preserveModules: false,
				// assetFileNames: "[name][extname]",
				// assetFileNames: (chunkInfo) => {
				//   console.debug("\n")
				//   console.debug(chunkInfo)
				//   return '[name][extname]'
				// },
			},
			external: externalize,
		},
	},
	css: {
		preprocessorOptions: {
			scss: {
				includePaths: [ 'client/stylesheets/abstracts' ],
				additionalData: ( source: string, filename: string ) => {
					// console.debug(filename);
					if ( filename.includes( 'node_modules' ) ) {
						/*
						 * Skip adding additional data for @automattic/* packages to
						 * fix "SassError: @use rules must be written before any other rules."
						 * @automattic/* packages have included '@use "sass:math" and other necessary imports.
						 */
						return source;
					}

					return `
            @import "node_modules/@wordpress/base-styles/_colors.scss";
            @import "node_modules/@wordpress/base-styles/_colors.native.scss";
            @import "node_modules/@wordpress/base-styles/_variables.scss";
            @import "node_modules/@wordpress/base-styles/_mixins.scss";
            @import "node_modules/@wordpress/base-styles/_breakpoints.scss";
            @import "node_modules/@wordpress/base-styles/_animations.scss";
            @import "node_modules/@wordpress/base-styles/_z-index.scss";
            @import "_colors";
            @import "_breakpoints";
            @import "_mixins";
            @import "_variables";
            ${ source }`;
				},
			},
		},
	},
} );
