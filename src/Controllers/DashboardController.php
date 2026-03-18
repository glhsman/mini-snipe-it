<?php
namespace App\Controllers;

use PDO;

class DashboardController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getStats() {
        // 1. Gesamt Seriennummern (Assets)
        $stmt = $this->db->query("SELECT COUNT(*) FROM assets");
        $totalAssets = $stmt->fetchColumn();

        // 2. Gerätetypen (Kategorien)
        $stmt = $this->db->query("SELECT COUNT(*) FROM categories");
        $totalCategories = $stmt->fetchColumn();

        // 3. Hersteller
        $stmt = $this->db->query("SELECT COUNT(*) FROM manufacturers");
        $totalManufacturers = $stmt->fetchColumn();

        // 4. Aktive Benutzer
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        $totalUsers = $stmt->fetchColumn();

        // 5. Status-Zähler (Einsatzbereit, Ausgegeben, etc.)
        $stmt = $this->db->query("SELECT s.name, COUNT(a.id) as count FROM assets a JOIN status_labels s ON a.status_id = s.id GROUP BY s.name");
        $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'total_assets' => $totalAssets,
            'total_categories' => $totalCategories,
            'total_manufacturers' => $totalManufacturers,
            'total_users' => $totalUsers,
            'status_counts' => $statusCounts
        ];
    }

    public function getCategoryDistribution() {
        // Verteilung nach Kategorie
        $sql = "SELECT c.name, COUNT(a.id) as count 
                FROM assets a 
                JOIN asset_models m ON a.model_id = m.id 
                JOIN categories c ON m.category_id = c.id 
                GROUP BY c.id, c.name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopManufacturers($limit = 5) {
        $sql = "SELECT m.name, COUNT(a.id) as count 
                FROM assets a 
                JOIN asset_models am ON a.model_id = am.id 
                JOIN manufacturers m ON am.manufacturer_id = m.id 
                GROUP BY m.id, m.name 
                ORDER BY count DESC 
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        // Da LIMIT in prepare Probleme machen kann (muss INT sein), binden wir es direkt
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
