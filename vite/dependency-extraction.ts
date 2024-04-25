// @ts-nocheck
/* eslint-disable */
import { createHash } from 'crypto';
import json2php from 'json2php';

const WORDPRESS_NAMESPACE = '@wordpress/';
const WOOCOMMERCE_NAMESPACE = '@woocommerce/';
const BUNDLED_PACKAGES = [
	'@wordpress/icons',
	'@wordpress/interface',
	'@wordpress/style-engine',
];

const camelCaseDash = ( string: string ) =>
	string.replace( /-([a-z])/g, ( _, char ) => char.toUpperCase() );

const isExternal = ( id: string ) => {
	switch ( id ) {
		case 'moment':
			return id;
		case '@babel/runtime/regenerator':
			return 'regeneratorRuntime';
		case 'lodash':
		case 'lodash-es':
			return 'lodash';
		case 'jquery':
			return 'jQuery';
		case 'react':
			return 'React';
		case 'react-dom':
			return 'ReactDOM';
		case 'wp-mediaelement':
			return [ 'wp', 'mediaelement' ];
		case '@woocommerce/block-data':
			return [ 'wc', 'wcBlocksData' ];
		case '@woocommerce/blocks-registry':
			return [ 'wc', 'wcBlocksRegistry' ];
		case '@woocommerce/settings':
			return [ 'wc', 'wcSettings' ];
	}

	if ( id.includes( 'react-refresh/runtime' ) ) {
		return 'ReactRefreshRuntime';
	}

	if ( BUNDLED_PACKAGES.includes( id ) ) {
		return undefined;
	}

	if ( id.startsWith( WORDPRESS_NAMESPACE ) ) {
		return [
			'wp',
			camelCaseDash( id.substring( WORDPRESS_NAMESPACE.length ) ),
		];
	}

	if ( id.startsWith( WOOCOMMERCE_NAMESPACE ) ) {
		return [
			'wc',
			camelCaseDash( id.substring( WOOCOMMERCE_NAMESPACE.length ) ),
		];
	}
};

// const isExternal = (id: string) =>
//   /(@wordpress|@woocommerce|wp-media|lodash|moment|react|gridicons)/.test(id);

// Avoid bundling external modules
export const externalize = ( id ) => {
	return isExternal( id );
	//   return d.externalizeWpDeps(undefined, id);
};

// Add external modules to global context
export const globalize = ( id: string ) => {
	const external = isExternal( id );
	if ( external ) {
		if ( Array.isArray( external ) ) {
			return external.join( '.' );
		}
		return external;
	}
};

// Generate the PHP deps file
export const assetize = () => {
	return {
		name: 'assetize',
		enforce: 'pre',
		generateBundle( options, bundle ) {
			Object.entries( bundle ).forEach( ( [ fileName, fileInfo ] ) => {
				if ( ! fileInfo.isAsset /*&& fileInfo.imports*/ ) {
					const { imports, code } = fileInfo;
					const scriptMeta = {
						dependencies: imports
							// .map(defaultRequestToHandle)
							.filter( ( o ) => o != null ),
						version: createHash( 'sha1' )
							.update( code )
							.digest( 'hex' )
							.slice( 0, 20 ),
					};
					this.emitFile( {
						type: 'asset',
						fileName: fileInfo.fileName.replace(
							/\.js$/,
							'.asset.php'
						),
						source: `<?php return ${ json2php( scriptMeta ) };\n`,
					} );
				}
			} );
		},
	};
};

export const list = {
	moment: 'moment',
	'@babel/runtime/regenerator': 'regeneratorRuntime',
	'lodash-es': 'lodash',
	lodash: 'lodash',
	jquery: 'jQuery',
	react: 'React',
	'react-dom': 'ReactDOM',
	'wp-mediaelement': 'wp.mediaelement',
	'@wordpress/components': 'wp.components',
	'@woocommerce/block-data': 'wc.wcBlocksData',
	'@woocommerce/blocks-registry': 'wc.wcBlocksRegistry',
	'@woocommerce/settings': 'wc.wcSettings',
	'@woocommerce/components': 'wc.components',
};
