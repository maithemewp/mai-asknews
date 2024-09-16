const mix     = require('laravel-mix');
const glob    = require('glob');
const path    = require('path');
const cssnano = require('cssnano');

// Process all CSS files in /src/css/ and output them to /build/css/
glob.sync('src/css/!(*.min).css').forEach(file => {
	const fileName = path.basename(file, '.css');

	// Process original CSS file and output to /build/css/ (unminified)
	mix.postCss(file, `build/css/${fileName}.css`, []);

	// Create a minified version directly by applying cssnano
	mix.postCss(`build/css/${fileName}.css`, `build/css/${fileName}.min.css`, [
		cssnano({
			preset: 'default',
		})
	]);
});

// Process all JS files in /src/js/ and output them to /build/js/
glob.sync('src/js/!(*.min).js').forEach(file => {
	const fileName = path.basename(file, '.js');

	// Process original JS file and output to /build/js/ (unminified)
	mix.js(file, `build/js/${fileName}.js`);

	// Minify the JS file and output as .min.js
	mix.minify(`build/js/${fileName}.js`);
});
