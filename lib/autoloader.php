<?php 	// lib/autoloader.php

spl_autoload_register(function ($class) {
	$prefix = 'CivivipGive\\';
	$base_path = trailingslashit(dirname(dirname(__FILE__) ) );	// i.e., ./../

	// Filter by our prefix.
	if (strncmp($prefix, $class, strlen($prefix) ) !== 0) {
		return;
	}

	// Assemble our filename.
	$relative_class = substr($class, strlen($prefix) ); $relative_class = strtolower($relative_class);
	$file = $base_path . str_replace('\\', '/', $relative_class) . '.php';
	if (file_exists($file) ) {
		require $file;
	}
});
