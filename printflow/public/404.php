<?php
/**
 * 404 Not Found Error Page
 * PrintFlow - Printing Shop PWA
 */

http_response_code(404);

require_once __DIR__ . '/../includes/auth.php';
$page_title = '404 - Page Not Found';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-16 text-center">
    <div class="max-w-2xl mx-auto">
        <div class="text-9xl font-bold text-indigo-600 mb-4">404</div>
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Page Not Found</h1>
        <p class="text-gray-600 mb-8">The page you're looking for doesn't exist or has been moved.</p>
        
        <div class="flex justify-center gap-4">
            <a href="<?php echo $url_index ?? '/printflow/'; ?>" class="btn-primary">Go Home</a>
            <a href="javascript:history.back()" class="btn-secondary">Go Back</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
