module.exports = {
	'*.{js,jsx,ts,tsx}': [ 'pnpm run format:provided', 'eslint' ],
	'*.{ts,tsx}': [ () => 'tsc --noEmit' ],
	'*.{scss,css}': [ 'pnpm run format:provided', 'stylelint' ],
	'*.php':
		'./vendor/bin/phpcs --standard=phpcs.xml.dist --basepath=. --colors',
	'composer.json': 'composer validate --strict --no-check-all',
};
