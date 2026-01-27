<?php
/**
 * Simple PSR-4 Autoloader
 *
 * Fallback autoloader when Composer is not available
 *
 * @package WPProductBuilder
 */

spl_autoload_register(function ($class) {
    // Project namespace prefix
    $prefix = 'WPProductBuilder\\';

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    // and append .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
