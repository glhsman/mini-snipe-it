<?php
namespace App\Controllers;

class AssetRequestController {
    private $db;
    private const IN_PROGRESS_PREFIX = '[IN_PROGRESS]';

    public function __construct($db) {
        $this->db = $db;
    }

    public function findUserByUsernameAndLocation(string $username, int $locationId): ?array {
        $username = trim($username);
        if ($username === '' || $locationId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT id, username, first_name, last_name, location_id
             FROM users
             WHERE username = ? AND location_id = ?
             LIMIT 2"
        );
        $stmt->execute([$username, $locationId]);
        $matches = $stmt->fetchAll();

        if (count($matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    public function createPublicRequest(int $locationId, string $username, int $categoryId, int $quantity, string $reason): array {
        $reason = trim($reason);
        $username = trim($username);

        if ($locationId <= 0 || $categoryId <= 0 || $quantity < 1 || $reason === '' || $username === '') {
            return ['success' => false, 'error' => 'Bitte alle Pflichtfelder korrekt ausfuellen.'];
        }

        $user = $this->findUserByUsernameAndLocation($username, $locationId);
        if (!$user) {
            return ['success' => false, 'error' => 'Benutzername und Standort konnten nicht zugeordnet werden.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Kategorie ist ungueltig.'];
        }

        $insert = $this->db->prepare(
            "INSERT INTO asset_requests (user_id, location_id, category_id, quantity, reason, status)
             VALUES (?, ?, ?, ?, ?, 'pending')"
        );
        $insert->execute([(int) $user['id'], $locationId, $categoryId, $quantity, $reason]);

        return ['success' => true, 'request_id' => (int) $this->db->lastInsertId()];
    }

    public function getRequestsFiltered(string $search = '', string $status = '', ?int $locationId = null, ?int $categoryId = null, int $limit = 250): array {
        $limit = max(1, min($limit, 500));
        $search = trim($search);

        $sql = "SELECT ar.*, u.username, u.first_name, u.last_name,
                       l.name AS location_name,
                       c.name AS category_name,
                       pu.username AS processed_by_username
                FROM asset_requests ar
                INNER JOIN users u ON u.id = ar.user_id
                INNER JOIN locations l ON l.id = ar.location_id
                INNER JOIN categories c ON c.id = ar.category_id
                LEFT JOIN users pu ON pu.id = ar.processed_by_user_id";

        $conditions = [];
        $params = [];

        if ($status !== '') {
            if ($status === 'open') {
                $conditions[] = "ar.status = 'pending'";
            } elseif ($status === 'in_progress') {
                $conditions[] = "ar.status = 'pending' AND ar.processed_at IS NOT NULL";
            } elseif (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                if ($status === 'pending') {
                    $conditions[] = "ar.status = 'pending' AND ar.processed_at IS NULL";
                } else {
                    $conditions[] = "ar.status = ?";
                    $params[] = $status;
                }
            }
        }

        if (!empty($locationId)) {
            $conditions[] = "ar.location_id = ?";
            $params[] = (int) $locationId;
        }

        if (!empty($categoryId)) {
            $conditions[] = "ar.category_id = ?";
            $params[] = (int) $categoryId;
        }

        if ($search !== '') {
            $conditions[] = "(
                u.username LIKE ?
                OR COALESCE(u.first_name, '') LIKE ?
                OR COALESCE(u.last_name, '') LIKE ?
                OR c.name LIKE ?
                OR l.name LIKE ?
                OR ar.reason LIKE ?
            )";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY ar.requested_at DESC, ar.id DESC LIMIT " . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRequestByIdWithUser(int $requestId): ?array {
        if ($requestId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT ar.*, u.username, u.first_name, u.last_name, u.email,
                    l.name AS location_name,
                    c.name AS category_name
             FROM asset_requests ar
             INNER JOIN users u ON u.id = ar.user_id
             INNER JOIN locations l ON l.id = ar.location_id
             INNER JOIN categories c ON c.id = ar.category_id
             WHERE ar.id = ?
             LIMIT 1"
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateRequestStatus(int $requestId, string $status, ?string $internalNote, int $processedByUserId): bool {
        if ($requestId <= 0 || $processedByUserId <= 0 || !in_array($status, ['in_progress', 'approved', 'rejected'], true)) {
            return false;
        }

        $note = $internalNote !== null ? trim($internalNote) : null;
        if ($note === '') {
            $note = null;
        }

        if ($status === 'in_progress') {
            return $this->markRequestInProgress($requestId, $note, $processedByUserId);
        }

        $note = $this->stripInProgressPrefix($note);

        $stmt = $this->db->prepare(
            "UPDATE asset_requests
             SET status = ?,
                 processed_at = NOW(),
                 processed_by_user_id = ?,
                 internal_note = ?
             WHERE id = ? AND status = 'pending'"
        );

        $stmt->execute([$status, $processedByUserId, $note, $requestId]);
        return $stmt->rowCount() > 0;
    }

    public function deriveDisplayStatus(array $row): string {
        $status = (string) ($row['status'] ?? 'pending');
        if ($status === 'pending' && !empty($row['processed_at'])) {
            return 'in_progress';
        }
        return $status;
    }

    public function stripInProgressPrefix(?string $note): ?string {
        if ($note === null) {
            return null;
        }

        $clean = preg_replace('/^' . preg_quote(self::IN_PROGRESS_PREFIX, '/') . '\\s*/', '', trim($note));
        if ($clean === null || trim($clean) === '') {
            return null;
        }

        return trim($clean);
    }

    private function markRequestInProgress(int $requestId, ?string $internalNote, int $processedByUserId): bool {
        $notePayload = self::IN_PROGRESS_PREFIX;
        if ($internalNote !== null) {
            $notePayload .= ' ' . $this->stripInProgressPrefix($internalNote);
        }

        $stmt = $this->db->prepare(
            "UPDATE asset_requests
             SET processed_at = NOW(),
                 processed_by_user_id = ?,
                 internal_note = ?
             WHERE id = ? AND status = 'pending'"
        );

        $stmt->execute([$processedByUserId, $notePayload, $requestId]);
        return $stmt->rowCount() > 0;
    }
}
