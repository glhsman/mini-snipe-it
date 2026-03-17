<?php
namespace App\Controllers;

use PDO;

class AssetController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAllAssets() {
        $query = "SELECT a.*, m.name as model_name, s.name as status_name, l.name as location_name, u.username as assigned_to, mf.name as manufacturer_name 
                  FROM assets a 
                  LEFT JOIN asset_models m ON a.model_id = m.id 
                  LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
                  LEFT JOIN status_labels s ON a.status_id = s.id 
                  LEFT JOIN locations l ON a.location_id = l.id 
                  LEFT JOIN users u ON a.user_id = u.id 
                  ORDER BY a.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAssetById($id) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createAsset($data) {
        $sql = "INSERT INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id, purchase_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], $data['asset_tag'], $data['serial'], $data['model_id'], 
            $data['status_id'], $data['location_id'], $data['user_id'] ?? null, 
            $data['purchase_date'], $data['notes']
        ]);
    }

    public function updateAsset($id, $data) {
        $sql = "UPDATE assets SET name=?, asset_tag=?, serial=?, model_id=?, status_id=?, location_id=?, user_id=?, purchase_date=?, notes=? 
                WHERE id=?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], $data['asset_tag'], $data['serial'], $data['model_id'], 
            $data['status_id'], $data['location_id'], $data['user_id'] ?? null, 
            $data['purchase_date'], $data['notes'], $id
        ]);
    }

    public function deleteAsset($id) {
        // Prüfen, ob Asset einem Benutzer zugewiesen ist
        $stmt = $this->db->prepare("SELECT user_id FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        $asset = $stmt->fetch();

        if ($asset && $asset['user_id'] !== null) {
            throw new \Exception("Asset ist einem Benutzer zugewiesen und kann nicht gelöscht werden.");
        }

        $stmt = $this->db->prepare("DELETE FROM assets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function generateAssetTag($locationId, $modelId) {
        // 1. Get Location Kuerzel
        $locKuerzel = 'XX';
        if ($locationId) {
            $stmt = $this->db->prepare("SELECT kuerzel FROM locations WHERE id = ?");
            $stmt->execute([$locationId]);
            $loc = $stmt->fetch();
            if ($loc && !empty($loc['kuerzel'])) {
                $locKuerzel = strtoupper($loc['kuerzel']);
            }
        }

        // 2. Get Model Kuerzel (jetzt aus Kategorie)
        $modKuerzel = 'XX';
        if ($modelId) {
            $stmt = $this->db->prepare("SELECT c.kuerzel FROM asset_models m JOIN categories c ON m.category_id = c.id WHERE m.id = ?");
            $stmt->execute([$modelId]);
            $mod = $stmt->fetch();
            if ($mod && !empty($mod['kuerzel'])) {
                $modKuerzel = strtoupper($mod['kuerzel']);
            }
        }

        $prefix = $locKuerzel . $modKuerzel; // z.B. MUDT

        // 3. Find highest number for this prefix
        $stmt = $this->db->prepare("SELECT asset_tag FROM assets WHERE asset_tag LIKE ? ORDER BY asset_tag DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $lastAsset = $stmt->fetch();

        $nextNumber = 1;
        if ($lastAsset) {
            $lastTag = $lastAsset['asset_tag'];
            $numPart = substr($lastTag, 4); 
            if (is_numeric($numPart)) {
                $nextNumber = (int)$numPart + 1;
            }
        }

        // 4. Formatieren mit führenden Nullen (4-stellig)
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
