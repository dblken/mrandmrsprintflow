<?php
// Try to reset OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successfully.";
} else {
    echo "OPcache is not enabled or function not available.";
}

// Also try to clear realpath cache
clearstatcache(true);
echo "<br>Realpath cache cleared.";

echo "<br>Done. Please try to Logout again.";
