module.exports = function () {
	const src = `new URL("${ this.resourcePath }", import.meta.url).toString()`;
	return `
	import React from 'react'
	export default function Image(props) {
		return <img src={${ src }} {...props} />
	}
	`;
};
