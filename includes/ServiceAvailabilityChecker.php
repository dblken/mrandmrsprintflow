<?php
/**
 * Service Availability Checker
 * Decides which services are shown based on material stock.
 * PrintFlow v2
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/InventoryManager.php';

class ServiceAvailabilityChecker {

    /**
     * Get all enabled services based on stock availability.
     */
    public static function getAvailableServices() {
        // 1. Get all service types defined in ENUM
        $services = [
            'Tarpaulin Printing',
            'T-shirt Printing',
            'Decals/Stickers (Print/Cut)',
            'Glass Stickers / Wall / Frosted Stickers',
            'Transparent Stickers',
            'Layouts',
            'Reflectorized (Subdivision Stickers/Signages)',
            'Stickers on Sintraboard',
            'Sintraboard Standees',
            'Souvenirs'
        ];

        $available = [];
        foreach ($services as $service) {
            if (self::isServiceAvailable($service)) {
                $available[] = $service;
            }
        }
        return $available;
    }

    /**
     * Checks if a service is available based on REQUIRED materials.
     */
    public static function isServiceAvailable($serviceType) {
        // 1. Get rules for this service
        $rules = db_query(
            "SELECT item_id, rule_type FROM service_material_rules WHERE service_type = ?",
            's',
            [$serviceType]
        ) ?: [];

        // If no rules defined (e.g. Layouts), service is ALWAYS available
        if (empty($rules)) return true;

        // Group by REQUIRED vs OPTIONAL
        $requiredItems = array_filter($rules, fn($r) => $r['rule_type'] === 'REQUIRED');
        
        // If there are required items, AT LEAST ONE must have stock > 0
        // (Logic: some services have multiple required item variants, e.g. T-shirt Printing 
        // can use White or Black shirts. If ANY one variant is in stock, the service is enabled.)
        if (!empty($requiredItems)) {
            $hasAnyRequired = false;
            foreach ($requiredItems as $rule) {
                $soh = InventoryManager::getStockOnHand($rule['item_id']);
                if ($soh > 0) {
                    $hasAnyRequired = true;
                    break; 
                }
            }
            if (!$hasAnyRequired) return false;
        }

        return true;
    }

    /**
     * Get material options for a specific service.
     */
    public static function getMaterialOptions($serviceType) {
        return db_query(
            "SELECT i.id, i.name, i.unit_of_measure, i.track_by_roll, r.rule_type 
             FROM service_material_rules r 
             JOIN inv_items i ON r.item_id = i.id 
             WHERE r.service_type = ? AND i.status = 'ACTIVE'
             ORDER BY r.rule_type DESC, i.name ASC",
            's',
            [$serviceType]
        ) ?: [];
    }
}
