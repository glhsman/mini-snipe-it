<?php
namespace App\Controllers;

use PDO;

class AssetController {
    private $db;
    private const MANUAL_ASSET_TAG_CATEGORY_CODES = ['DT', 'NB'];
    private const BLOCKED_ASSIGNED_STATUS_NAMES = ['in reparatur', 'ausgemustert', 'defekt'];

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAllAssets() {
        $query = "SELECT a.*, m.name as model_name, s.name as status_name, s.status_type as status_type, l.name as location_name, u.username as assigned_to, mf.name as manufacturer_name 
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

    public function countAssetsFiltered($search, $modelId, $statusId = null) {
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
        if (!empty($statusId)) {
            $conditions[] = "a.status_id = ?";
            $params[] = (int)$statusId;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM assets a $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getAssetsPaginated($limit, $offset) {
        return $this->getAssetsPaginatedFiltered('', null, $limit, $offset);
    }

    public function getAssetsPaginatedFiltered($search, $modelId, $limit, $offset, $sort = 'created_at', $order = 'DESC', $statusId = null) {
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
        if (!empty($statusId)) {
            $conditions[] = "a.status_id = ?";
            $params[] = (int)$statusId;
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
        $query = "SELECT a.*, m.name as model_name, s.name as status_name, s.status_type as status_type, l.name as location_name, u.username as assigned_to, mf.name as manufacturer_name 
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
        $stmt = $this->db->prepare("SELECT a.*, s.name AS status_name, s.status_type AS status_type
                                   FROM assets a
                                   LEFT JOIN status_labels s ON a.status_id = s.id
                                   WHERE a.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAssetsByUserId($userId) {
                $stmt = $this->db->prepare("SELECT a.*, m.name AS model_name, s.name AS status_name, s.status_type AS status_type, aa.checkout_at AS assigned_at
                                   FROM assets a
                                   LEFT JOIN asset_models m ON a.model_id = m.id
                                   LEFT JOIN status_labels s ON a.status_id = s.id
                                                                     LEFT JOIN asset_assignments aa ON aa.id = (
                                                                             SELECT aa2.id
                                                                             FROM asset_assignments aa2
                                                                             WHERE aa2.asset_id = a.id
                                                                                 AND aa2.checkin_at IS NULL
                                                                             ORDER BY aa2.checkout_at DESC, aa2.id DESC
                                                                             LIMIT 1
                                                                     )
                                   WHERE a.user_id = ?
                                   ORDER BY COALESCE(a.asset_tag, a.serial, a.name, '') ASC, a.id ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getAssignmentById($assignmentId) {
        $stmt = $this->db->prepare("SELECT aa.*, a.name AS asset_name, a.asset_tag, a.serial, m.name AS model_name,
                                           u.username, u.first_name, u.last_name, u.email,
                                           l.name AS location_name, l.address AS location_address, l.city AS location_city
                                    FROM asset_assignments aa
                                    INNER JOIN assets a ON aa.asset_id = a.id
                                    LEFT JOIN asset_models m ON a.model_id = m.id
                                    INNER JOIN users u ON aa.user_id = u.id
                                    LEFT JOIN locations l ON u.location_id = l.id
                                    WHERE aa.id = ?");
        $stmt->execute([$assignmentId]);
        return $stmt->fetch();
    }

    public function getAssignmentsByIds(array $assignmentIds) {
        $assignmentIds = array_values(array_filter(array_map('intval', $assignmentIds), static fn($id) => $id > 0));
        if (empty($assignmentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
        $sql = "SELECT aa.*, a.name AS asset_name, a.asset_tag, a.serial, m.name AS model_name,
                       u.username, u.first_name, u.last_name, u.email,
                       l.name AS location_name, l.address AS location_address, l.city AS location_city
                FROM asset_assignments aa
                INNER JOIN assets a ON aa.asset_id = a.id
                LEFT JOIN asset_models m ON a.model_id = m.id
                INNER JOIN users u ON aa.user_id = u.id
                LEFT JOIN locations l ON u.location_id = l.id
                WHERE aa.id IN ($placeholders)
                ORDER BY aa.checkout_at ASC, aa.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($assignmentIds);
        return $stmt->fetchAll();
    }

    public function getAssignmentHistory(int $limit = 250, string $search = ''): array {
        $limit = max(1, min($limit, 1000));
        $search = trim($search);

        $params = [];
        $conditions = [];
        if ($search !== '') {
            $conditions[] = "(
                COALESCE(a.asset_tag, '') LIKE ?
                OR COALESCE(a.serial, '') LIKE ?
                OR COALESCE(a.name, '') LIKE ?
                OR COALESCE(u.username, '') LIKE ?
                OR COALESCE(u.first_name, '') LIKE ?
                OR COALESCE(u.last_name, '') LIKE ?
            )";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term, $term, $term, $term];
        }

        $whereSql = '';
        $checkinWhereSql = ' WHERE aa.checkin_at IS NOT NULL';
        if (!empty($conditions)) {
            $conditionSql = implode(' AND ', $conditions);
            $whereSql = ' WHERE ' . $conditionSql;
            $checkinWhereSql = ' WHERE ' . $conditionSql . ' AND aa.checkin_at IS NOT NULL';
        }

        $sql = "SELECT *
                FROM (
                    SELECT aa.id,
                           aa.asset_id,
                           aa.user_id,
                           aa.checkout_at,
                           aa.checkin_at,
                           aa.checkout_at AS booking_at,
                           'checkout' AS booking_type,
                           a.asset_tag,
                           a.serial,
                           a.name AS asset_name,
                           m.name AS model_name,
                           u.username,
                           u.first_name,
                           u.last_name,
                           cbu.username AS operator_username
                    FROM asset_assignments aa
                    INNER JOIN assets a ON a.id = aa.asset_id
                    LEFT JOIN asset_models m ON m.id = a.model_id
                    INNER JOIN users u ON u.id = aa.user_id
                    LEFT JOIN users cbu ON cbu.id = aa.checkout_by_user_id
                    $whereSql

                    UNION ALL

                    SELECT aa.id,
                           aa.asset_id,
                           aa.user_id,
                           aa.checkout_at,
                           aa.checkin_at,
                           aa.checkin_at AS booking_at,
                           'checkin' AS booking_type,
                           a.asset_tag,
                           a.serial,
                           a.name AS asset_name,
                           m.name AS model_name,
                           u.username,
                           u.first_name,
                           u.last_name,
                           ibu.username AS operator_username
                    FROM asset_assignments aa
                    INNER JOIN assets a ON a.id = aa.asset_id
                    LEFT JOIN asset_models m ON m.id = a.model_id
                    INNER JOIN users u ON u.id = aa.user_id
                    LEFT JOIN users ibu ON ibu.id = aa.checkin_by_user_id
                    $checkinWhereSql
                ) booking_history
                ORDER BY booking_at DESC, id DESC, booking_type DESC
                LIMIT " . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, $params));
        return $stmt->fetchAll();
    }

    public function deleteAssignmentHistoryEntry(int $assignmentId): bool {
        if ($assignmentId <= 0) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM asset_assignments WHERE id = ? LIMIT 1");
            $stmt->execute([$assignmentId]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                throw new \RuntimeException('Buchung nicht gefunden.');
            }

            $isOpenAssignment = empty($assignment['checkin_at']);
            if ($isOpenAssignment) {
                $assetStmt = $this->db->prepare("SELECT user_id FROM assets WHERE id = ? LIMIT 1");
                $assetStmt->execute([(int) $assignment['asset_id']]);
                $asset = $assetStmt->fetch();

                if ($asset && (int) ($asset['user_id'] ?? 0) === (int) $assignment['user_id']) {
                    $updateAssetStmt = $this->db->prepare("UPDATE assets SET user_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateAssetStmt->execute([(int) $assignment['asset_id']]);
                }
            }

            $deleteStmt = $this->db->prepare("DELETE FROM asset_assignments WHERE id = ?");
            $deleteStmt->execute([$assignmentId]);

            $this->db->commit();
            return $deleteStmt->rowCount() > 0;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function getOpenAssignmentForAsset($assetId) {
        $stmt = $this->db->prepare("SELECT * FROM asset_assignments WHERE asset_id = ? AND checkin_at IS NULL ORDER BY checkout_at DESC, id DESC LIMIT 1");
        $stmt->execute([$assetId]);
        return $stmt->fetch();
    }

    public function checkoutAsset($assetId, $userId, $operatorId = null, ?string $assetTag = null) {
        $this->db->beginTransaction();
        try {
            $asset = $this->getAssetById($assetId);
            if (!$asset) {
                throw new \RuntimeException('Asset nicht gefunden.');
            }

            $statusName = strtolower(trim((string) ($asset['status_name'] ?? '')));
            if ($statusName !== 'einsatzbereit') {
                throw new \RuntimeException('Asset ist nicht einsatzbereit und kann nicht ausgegeben werden.');
            }
            if (!empty($asset['user_id'])) {
                throw new \RuntimeException('Asset ist bereits ausgegeben.');
            }

            $openAssignment = $this->getOpenAssignmentForAsset($assetId);
            if ($openAssignment) {
                $stmt = $this->db->prepare("UPDATE asset_assignments SET checkin_at = NOW(), checkin_by_user_id = ? WHERE id = ?");
                $stmt->execute([$operatorId, $openAssignment['id']]);
            }

            $issuedStatusId = $this->getStatusIdByName('Ausgegeben');

            $existingTag = trim((string) ($asset['asset_tag'] ?? ''));
            if ($existingTag === '' && $assetTag !== null && $assetTag !== '') {
                $stmt = $this->db->prepare("UPDATE assets SET user_id = ?, status_id = ?, asset_tag = ?, archiv_bit = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$userId, $issuedStatusId, $assetTag, $assetId]);
            } else {
                $stmt = $this->db->prepare("UPDATE assets SET user_id = ?, status_id = ?, archiv_bit = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$userId, $issuedStatusId, $assetId]);
            }

            $stmt = $this->db->prepare("INSERT INTO asset_assignments (asset_id, user_id, checkout_by_user_id) VALUES (?, ?, ?)");
            $stmt->execute([$assetId, $userId, $operatorId]);

            $assignmentId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $assignmentId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function checkinAsset($assetId, $operatorId = null) {
        $this->db->beginTransaction();
        try {
            $asset = $this->getAssetById($assetId);
            if (!$asset) {
                throw new \RuntimeException('Asset nicht gefunden.');
            }

            $openAssignment = $this->getOpenAssignmentForAsset($assetId);
            if ($openAssignment) {
                $assignmentId = (int) $openAssignment['id'];
                $stmt = $this->db->prepare("UPDATE asset_assignments SET checkin_at = NOW(), checkin_by_user_id = ? WHERE id = ?");
                $stmt->execute([$operatorId, $assignmentId]);
            } elseif (!empty($asset['user_id'])) {
                $stmt = $this->db->prepare("INSERT INTO asset_assignments (asset_id, user_id, checkout_at, checkout_by_user_id, checkin_at, checkin_by_user_id) VALUES (?, ?, NOW(), NULL, NOW(), ?)");
                $stmt->execute([$assetId, $asset['user_id'], $operatorId]);
                $assignmentId = (int) $this->db->lastInsertId();
            } else {
                throw new \RuntimeException('Asset ist keinem Benutzer zugewiesen.');
            }

            $readyStatusId = $this->getStatusIdByName('Einsatzbereit');
            $stmt = $this->db->prepare("UPDATE assets SET user_id = NULL, status_id = ?, archiv_bit = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$readyStatusId, $assetId]);

            $this->db->commit();
            return $assignmentId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createAsset($data) {
        $this->validateAssetStateRules($data);

        $modelId = !empty($data['model_id']) ? (int)$data['model_id'] : null;
        $serialRequired = array_key_exists('serial_number_required', $data)
            ? ((int)$data['serial_number_required'] === 1 ? 1 : 0)
            : ($this->isSerialNumberRequiredForModel($modelId) ? 1 : 0);
        $serial = strtoupper(trim((string)($data['serial'] ?? '')));

        if ($serial === '' && $serialRequired === 0) {
            $serial = $this->generatePlaceholderSerial();
        }

        $sql = "INSERT INTO assets (name, asset_tag, serial, serial_number_required, model_id, status_id, location_id, user_id, purchase_date, notes, pin, puk, rufnummer, mac_adresse, ram, ssd_size, cores, os_version, archiv_bit) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], $data['asset_tag'], $serial, $serialRequired, $data['model_id'], 
            $data['status_id'], $data['location_id'], $data['user_id'] ?? null, 
            $data['purchase_date'], $data['notes'],
            $data['pin'] ?? null, $data['puk'] ?? null, $data['rufnummer'] ?? null,
            $data['mac_adresse'] ?? null, $data['ram'] ?? null, $data['ssd_size'] ?? null,
            $data['cores'] ?? null, $data['os_version'] ?? null, 0
        ]);
    }

    public function updateAsset($id, $data) {
        $this->validateAssetStateRules($data, (int)$id);

        $modelId = !empty($data['model_id']) ? (int)$data['model_id'] : null;
        $serialRequired = array_key_exists('serial_number_required', $data)
            ? ((int)$data['serial_number_required'] === 1 ? 1 : 0)
            : ($this->isSerialNumberRequiredForModel($modelId) ? 1 : 0);
        $serial = strtoupper(trim((string)($data['serial'] ?? '')));

        if ($serial === '' && $serialRequired === 0) {
            $serial = $this->generatePlaceholderSerial();
        }

        $sql = "UPDATE assets SET name=?, asset_tag=?, serial=?, serial_number_required=?, model_id=?, status_id=?, location_id=?, user_id=?, purchase_date=?, notes=?, pin=?, puk=?, rufnummer=?, mac_adresse=?, ram=?, ssd_size=?, cores=?, os_version=?, archiv_bit=0 
                WHERE id=?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'], $data['asset_tag'], $serial, $serialRequired, $data['model_id'], 
            $data['status_id'], $data['location_id'], $data['user_id'] ?? null, 
            $data['purchase_date'], $data['notes'],
            $data['pin'] ?? null, $data['puk'] ?? null, $data['rufnummer'] ?? null,
            $data['mac_adresse'] ?? null, $data['ram'] ?? null, $data['ssd_size'] ?? null,
            $data['cores'] ?? null, $data['os_version'] ?? null,
            $id
        ]);
    }

    public function isSerialNumberRequiredForModel($modelId) {
        if (empty($modelId)) {
            return true;
        }

        $stmt = $this->db->prepare("SELECT serial_number_required FROM asset_models WHERE id = ?");
        $stmt->execute([(int)$modelId]);
        $value = $stmt->fetchColumn();

        return $value === false ? true : ((int)$value === 1);
    }

    public function generatePlaceholderSerial() {
        do {
            $serial = 'NA-' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->assetSerialExists($serial));

        return $serial;
    }

    private function assetSerialExists($serial) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM assets WHERE serial = ?");
        $stmt->execute([$serial]);
        return (int)$stmt->fetchColumn() > 0;
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
        if ($this->requiresManualAssetTag($modelId)) {
            throw new \RuntimeException("Für Assets der Kategorien 'DT' und 'NB' muss das Asset-Tag manuell vergeben werden.");
        }

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
    public function shouldAutoGenerateAssetTag($modelId): bool {
        return !$this->requiresManualAssetTag($modelId);
    }

    private function validateAssetStateRules(array &$data, ?int $assetId = null): void {
        $data['asset_tag'] = trim((string)($data['asset_tag'] ?? ''));

        if ($data['asset_tag'] === '' && $this->requiresManualAssetTag($data['model_id'] ?? null)) {
            throw new \RuntimeException("Für Assets der Kategorien 'DT' und 'NB' darf kein Asset-Tag automatisch generiert werden. Bitte ein manuell vergebenes Asset-Tag eintragen.");
        }

        $userId = !empty($data['user_id']) ? (int)$data['user_id'] : 0;
        $statusName = $this->getStatusNameById($data['status_id'] ?? null);

        if ($userId > 0 && in_array($statusName, self::BLOCKED_ASSIGNED_STATUS_NAMES, true)) {
            throw new \RuntimeException("Ein zugeordnetes Asset kann nicht auf 'In Reparatur', 'Defekt' oder 'Ausgemustert' gesetzt werden.");
        }

        if ($assetId !== null && $assetId > 0 && $userId === 0) {
            $asset = $this->getAssetById($assetId);
            if ($asset && !empty($asset['user_id']) && in_array($statusName, self::BLOCKED_ASSIGNED_STATUS_NAMES, true)) {
                throw new \RuntimeException("Ein zugeordnetes Asset kann nicht auf 'In Reparatur', 'Defekt' oder 'Ausgemustert' gesetzt werden.");
            }
        }
    }

    private function requiresManualAssetTag($modelId): bool {
        $categoryCode = $this->getCategoryCodeByModelId($modelId);
        return in_array($categoryCode, self::MANUAL_ASSET_TAG_CATEGORY_CODES, true);
    }

    private function getCategoryCodeByModelId($modelId): string {
        if (empty($modelId)) {
            return '';
        }

        $stmt = $this->db->prepare("SELECT UPPER(COALESCE(c.kuerzel, ''))
                                    FROM asset_models m
                                    LEFT JOIN categories c ON m.category_id = c.id
                                    WHERE m.id = ?
                                    LIMIT 1");
        $stmt->execute([(int)$modelId]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? trim($value) : '';
    }

    private function getStatusNameById($statusId): string {
        if (empty($statusId)) {
            return '';
        }

        $stmt = $this->db->prepare("SELECT LOWER(TRIM(name)) FROM status_labels WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$statusId]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? trim($value) : '';
    }

    private function getStatusIdByName(string $statusName): int {
        $stmt = $this->db->prepare("SELECT id FROM status_labels WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->execute([$statusName]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            throw new \RuntimeException("Status '" . $statusName . "' wurde nicht gefunden.");
        }

        return (int) $value;
    }
}
