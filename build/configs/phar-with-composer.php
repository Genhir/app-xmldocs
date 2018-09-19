<?php

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

$config = [
	'sourcesDir'			=> __DIR__ . '/../development',
	'releaseFile'			=> __DIR__ . '/../release/index.php',
	// do not include script or file, where it's relative path from sourceDir match any of these rules:
	'excludePatterns'		=> [
		// Common excludes for every MvcCore app using composer:
		"#/\.#",										// Everything started with '.' (.git, .htaccess ...)
		"#\.(git|hg|svn|bak|bat|cmd|sh|md|txt|json|lock|phpt|config|htaccess|htpasswd|phpproj|phpproj.user)$#i",
		"#^/Var/Logs/.*#",								// App development logs

		// Exclude specific PHP libraries
		"^/vendor/tracy/.*",							// - tracy library (https://tracy.nette.org/)
		"^/vendor/tracy/tracy/(.*)/assets/",			//   excluded everything except staticly
		"^/vendor/tracy/tracy/tools/",					//   loaded PHP scripts by composer - added later
		"^/vendor/mvccore/ext-debug-tracy.*",			// - mvccore tracy adapter and all tracy panel extensions
		"^/vendor/mrclay/.*",							// HTML/JS/CSS minify library

		// Exclude source css and js files, use only what is generated in '/Var/Tmp' dir
		"^/static/js",
		"^/static/css",
		"^/static/fonts/(.*)\.css$",
	],
	// include all scripts or files, where it's relative path from sourceDir match any of these rules:
	// (include paterns always overides exclude patterns)
	'includePatterns'		=> [
		// add staticly included tracy file back again and override it's exclusion,
		// to run composer at application start properly, but this file will not be used.
		"#^/vendor/mrclay/minify/min/lib/Minify/HTML#",
		"#^/vendor/mobiledetect/mobiledetectlib/Mobile_Detect\.php$#",
	],
	// process simple strings replacements on all readed PHP scripts before saving into result package:
	// (replacements are executed before configured minification in RAM, they don't affect anythin on hard drive)
	'stringReplacements'	=> [
		// Switch MvcCore application back from SFU mode to automatic compile mode detection
		'->Run(1);'		=> '->Run();',
		// Remove tracy debug library extension usage (optional):
		"class_exists('\MvcCore\Ext\Debugs\Tracy')"	=> 'FALSE',
	],
	'minifyTemplates'		=> 1,// Remove non-conditional comments and whitespaces
	'minifyPhp'				=> 1,// Remove comments and whitespaces
];
