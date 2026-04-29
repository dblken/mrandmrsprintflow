/**
 * Legacy service worker compatibility shim.
 *
 * Some previously-installed clients may still be registered against `sw.js`.
 * Keep that registration alive by loading the current PHP-backed worker so
 * background push continues to work even before the browser fully migrates.
 */

importScripts('./sw.php');
