<?php
/**
 * Support chat widget — business info API
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Load configs from footer
    $shop_path   = __DIR__ . '/../assets/uploads/shop_config.json';
    $footer_path = __DIR__ . '/../assets/uploads/footer_config.json';
    
    $shop   = file_exists($shop_path)   ? (json_decode(file_get_contents($shop_path),   true) ?: []) : [];
    $footer = file_exists($footer_path) ? (json_decode(file_get_contents($footer_path), true) ?: []) : [];
    
    $name     = !empty($shop['name'])               ? htmlspecialchars($shop['name'])              : 'PrintFlow';
    $email    = !empty($footer['email'])            ? htmlspecialchars($footer['email'])           : (!empty($shop['email'])  ? htmlspecialchars($shop['email'])  : 'contact@printflow.com');
    $phone    = !empty($footer['phone'])            ? htmlspecialchars($footer['phone'])           : (!empty($shop['phone'])  ? htmlspecialchars($shop['phone'])  : '');
    $hours    = !empty($footer['hours'])            ? htmlspecialchars($footer['hours'])           : '';
    $services = !empty($footer['services'])         ? $footer['services']                          : [];
    $branches = !empty($footer['branch_addresses']) ? $footer['branch_addresses']                  : [];

    echo json_encode([
        'success' => true,
        'data' => [
            'business_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'hours' => $hours,
            'services' => array_map(function($s) { return htmlspecialchars($s); }, $services),
            'branches' => array_map(function($b) { 
                return [
                    'name' => !empty($b['name']) ? htmlspecialchars($b['name']) : 'Branch',
                    'address' => !empty($b['address']) ? htmlspecialchars($b['address']) : ''
                ];
            }, $branches)
        ]
    ], JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading support chat info'
    ]);
}
?>
