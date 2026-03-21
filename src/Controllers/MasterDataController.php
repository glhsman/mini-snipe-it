<?php
namespace App\Controllers;

use PDO;

class MasterDataController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getLocations() {
        $stmt = $this->db->query("SELECT * FROM locations ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getManufacturers() {
        $stmt = $this->db->query("SELECT * FROM manufacturers ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getCategories() {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getStatusLabels() {
        $stmt = $this->db->query("SELECT * FROM status_labels 
                                  ORDER BY CASE name 
                                      WHEN 'Einsatzbereit' THEN 1 
                                      WHEN 'Ausgegeben' THEN 2 
                                      WHEN 'In Reparatur' THEN 3 
                                      WHEN 'Defekt' THEN 4 
                                      ELSE 5 
                                  END, name");
        return $stmt->fetchAll();
    }

    public function getAssetModels() {
        $stmt = $this->db->query("SELECT m.*, ma.name as manufacturer_name, c.name as category_name, c.kuerzel as category_kuerzel
                                  FROM asset_models m 
                                  LEFT JOIN manufacturers ma ON m.manufacturer_id = ma.id 
                                  LEFT JOIN categories c ON m.category_id = c.id 
                                  ORDER BY COALESCE(ma.name, ''), m.name");
        return $stmt->fetchAll();
    }

    public function getLocationById($id) {
        $stmt = $this->db->prepare("SELECT * FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAssetModelById($id) {
        $stmt = $this->db->prepare("SELECT * FROM asset_models WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getCategoryById($id) {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getManufacturerById($id) {
        $stmt = $this->db->prepare("SELECT * FROM manufacturers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // --- Locations ---
    public function addLocation($data) {
        $sql = "INSERT INTO locations (name, address, city, kuerzel) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$data['name'], $data['address'] ?? null, $data['city'] ?? null, $data['kuerzel'] ?? null]);
    }

    public function updateLocation($id, $data) {
        $sql = "UPDATE locations SET name = ?, address = ?, city = ?, kuerzel = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$data['name'], $data['address'] ?? null, $data['city'] ?? null, $data['kuerzel'] ?? null, $id]);
    }

    public function deleteLocation($id) {
        $stmt = $this->db->prepare("DELETE FROM locations WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Asset Models ---
    public function addAssetModel($data) {
        $sql = "INSERT INTO asset_models (name, manufacturer_id, category_id, model_number, serial_number_required, has_sim_fields, has_hardware_fields) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], 
            $data['manufacturer_id'] ?: null, 
            $data['category_id'] ?: null, 
            $data['model_number'] ?? null,
            $data['serial_number_required'] ?? 1,
            $data['has_sim_fields'] ?? 0,
            $data['has_hardware_fields'] ?? 0
        ]);
    }

    public function updateAssetModel($id, $data) {
        $sql = "UPDATE asset_models SET name = ?, manufacturer_id = ?, category_id = ?, model_number = ?, serial_number_required = ?, has_sim_fields = ?, has_hardware_fields = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], 
            $data['manufacturer_id'] ?: null, 
            $data['category_id'] ?: null, 
            $data['model_number'] ?? null, 
            $data['serial_number_required'] ?? 1,
            $data['has_sim_fields'] ?? 0,
            $data['has_hardware_fields'] ?? 0,
            $id
        ]);
    }

    public function deleteAssetModel($id) {
        $stmt = $this->db->prepare("DELETE FROM asset_models WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Categories ---
    public function createCategory($data) {
        try {
            $stmt = $this->db->prepare("INSERT INTO categories (name, kuerzel) VALUES (?, ?)");
            return $stmt->execute([$data['name'], strtoupper($data['kuerzel'] ?? '')]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), '1062') !== false) {
                throw new \Exception("Das Kürzel '" . strtoupper($data['kuerzel']) . "' wird bereits von einer anderen Kategorie verwendet.");
            }
            throw $e;
        }
    }
    public function updateCategory($id, $data) {
        try {
            $stmt = $this->db->prepare("UPDATE categories SET name = ?, kuerzel = ? WHERE id = ?");
            return $stmt->execute([$data['name'], strtoupper($data['kuerzel'] ?? ''), $id]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), '1062') !== false) {
                throw new \Exception("Das Kürzel '" . strtoupper($data['kuerzel']) . "' wird bereits von einer anderen Kategorie verwendet.");
            }
            throw $e;
        }
    }
    public function deleteCategory($id) {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Manufacturers ---
    public function createManufacturer($name) {
        $stmt = $this->db->prepare("INSERT INTO manufacturers (name) VALUES (?)");
        return $stmt->execute([$name]);
    }
    public function updateManufacturer($id, $name) {
        $stmt = $this->db->prepare("UPDATE manufacturers SET name = ? WHERE id = ?");
        return $stmt->execute([$name, $id]);
    }
    public function deleteManufacturer($id) {
        $stmt = $this->db->prepare("DELETE FROM manufacturers WHERE id = ?");
        return $stmt->execute([$id]);
    }
    // --- Hardware Lookups ---
    public function getLookupOptions($type) {
        $allowed = ['ram', 'ssd', 'cores', 'os'];
        if (!in_array($type, $allowed)) return [];
        
        $table = "lookup_" . $type;
        $orderBy = ($type === 'os') ? "value" : "CAST(value AS UNSIGNED), value";
        
        $stmt = $this->db->query("SELECT * FROM $table ORDER BY $orderBy");
        return $stmt->fetchAll();
    }

    public function addLookupOption($type, $value) {
        $allowed = ['ram', 'ssd', 'cores', 'os'];
        if (!in_array($type, $allowed)) return false;
        
        $table = "lookup_" . $type;
        $stmt = $this->db->prepare("INSERT INTO $table (value) VALUES (?)");
        return $stmt->execute([trim($value)]);
    }

    public function deleteLookupOption($type, $id) {
        $allowed = ['ram', 'ssd', 'cores', 'os'];
        if (!in_array($type, $allowed)) return false;
        
        $table = "lookup_" . $type;
        $stmt = $this->db->prepare("DELETE FROM $table WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
