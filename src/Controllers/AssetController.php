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

    public function countAssets() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM assets");
        return (int) $stmt->fetchColumn();
    }

    public function countAssetsFiltered($search, $modelId) {
        $conditions = [];
        $params     = [];
        if (!empty($search)) {
            $conditions[] = "(a.asset_tag LIKE ? OR a.serial LIKE ? OR a.name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($modelId)) {
            $conditions[] = "a.model_id = ?";
            $params[] = (int)$modelId;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM assets a $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getAssetsPaginated($limit, $offset) {
        return $this->getAssetsPaginatedFiltered('', null, $limit, $offset);
    }

    public function getAssetsPaginatedFiltered($search, $modelId, $limit, $offset, $sort = 'created_at', $order = 'DESC') {
        $conditions = [];
        $params     = [];
        if (!empty($search)) {
            $conditions[] = "(a.asset_tag LIKE ? OR a.serial LIKE ? OR a.name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($modelId)) {
            $conditions[] = "a.model_id = ?";
            $params[] = (int)$modelId;
        }
        
        // Sorting Whitelist
        $allowedSort = ['name', 'asset_tag', 'model_name', 'manufacturer_name', 'status_name', 'location_name', 'id', 'created_at'];
        $sort = in_array(strtolower($sort), $allowedSort) ? strtolower($sort) : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sortMap = [
            'name' => 'a.name',
            'asset_tag' => 'a.asset_tag',
            'id' => 'a.id',
            'created_at' => 'a.created_at',
            'model_name' => 'm.name',
            'manufacturer_name' => 'mf.name',
            'status_name' => 's.name',
            'location_name' => 'l.name'
        ];
        $orderBy = $sortMap[$sort] ?? 'a.created_at';

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $query = "SELECT a.*, m.name as model_name, s.name as status_name, l.name as location_name, u.username as assigned_to, mf.name as manufacturer_name 
                  FROM assets a 
                  LEFT JOIN asset_models m ON a.model_id = m.id 
                  LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
                  LEFT JOIN status_labels s ON a.status_id = s.id 
                  LEFT JOIN locations l ON a.location_id = l.id 
                  LEFT JOIN users u ON a.user_id = u.id
                  $where
                  ORDER BY $orderBy $order
                  LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p, is_int($p) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, (int)$limit,  \PDO::PARAM_INT);
        $stmt->bindValue($i,   (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAssetById($id) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createAsset($data) {
        $sql = "INSERT INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id, purchase_date, notes, pin, puk, rufnummer, mac_adresse, ram, ssd_size, cores, os_version) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], $data['asset_tag'], $data['serial'], $data['model_id'], 
            $data['status_id'], $data['location_id'], $data['user_id'] ?? null, 
            $data['purchase_date'], $data['notes'],
            $data['pin'] ?? null, $data['puk'] ?? null, $data['rufnummer'] ?? null,
            $data['mac_adresse'] ?? null, $data['ram'] ?? null, $data['ssd_size'] ?? null,
            $data['cores'] ?? null, $data['os_version'] ?? null
        ]);
    }

    public function updateAsset($id, $data) {
        $sql = "UPDATE assets SET name=?, asset_tag=?, serial=?, model_id=?, status_id=?, location_id=?, user_id=?, purchase_date=?, notes=?, pin=?, puk=?, rufnummer=?, mac_adresse=?, ram=?, ssd_size=?, cores=?, os_version=? 
                WHERE id=?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], $data['asset_tag'], $data['serial'], $data['model_id'], 
            $data['status_id'], $data['location_id'], $data['user_id'] ?? null, 
            $data['purchase_date'], $data['notes'],
            $data['pin'] ?? null, $data['puk'] ?? null, $data['rufnummer'] ?? null,
            $data['mac_adresse'] ?? null, $data['ram'] ?? null, $data['ssd_size'] ?? null,
            $data['cores'] ?? null, $data['os_version'] ?? null,
            $id
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
