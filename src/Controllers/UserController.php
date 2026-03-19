<?php
namespace App\Controllers;

use PDO;

class UserController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAllUsers() {
        $query = "SELECT u.*, l.name as location_name 
                  FROM users u 
                  LEFT JOIN locations l ON u.location_id = l.id 
                  ORDER BY u.last_name, u.first_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countUsers() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn();
    }

    public function getUsersPaginated($limit, $offset) {
        $query = "SELECT u.*, l.name as location_name
                  FROM users u
                  LEFT JOIN locations l ON u.location_id = l.id
                  ORDER BY u.last_name, u.first_name
                  LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createUser($data) {
        $sql = "INSERT INTO users (first_name, last_name, email, username, password, location_id, can_login, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $canLogin = array_key_exists('can_login', $data) ? (!empty($data['can_login']) ? 1 : 0) : 1;
        $password = $data['password'] ?? '';
        $hashedPassword = null;
        $role = $data['role'] ?? 'user';
        if (!in_array($role, ['admin', 'editor', 'user'], true)) {
            $role = 'user';
        }
        if ($canLogin && trim($password) !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $email = (isset($data['email']) && trim($data['email']) !== '') ? $data['email'] : null;
        $firstName = (isset($data['first_name']) && trim($data['first_name']) !== '') ? $data['first_name'] : null;
        $lastName = (isset($data['last_name']) && trim($data['last_name']) !== '') ? $data['last_name'] : null;

        $stmt->bindValue(1, $firstName, $firstName !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(2, $lastName, $lastName !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(3, $email, $email !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(4, $data['username'], \PDO::PARAM_STR);
        $stmt->bindValue(5, $hashedPassword, $hashedPassword !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(6, $data['location_id'], $data['location_id'] !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->bindValue(7, $canLogin, \PDO::PARAM_INT);
        $stmt->bindValue(8, $role, \PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function authenticate($username, $password) {
        $result = $this->authenticateDetailed($username, $password);
        return $result['success'] ? $result['user'] : false;
    }

    public function authenticateDetailed($username, $password) {
        $username = trim((string) $username);
        $password = (string) $password;

        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'reason' => 'missing_credentials',
                'user' => null,
                'user_id' => null,
                'username' => $username,
            ];
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return [
                'success' => false,
                'reason' => 'unknown_username',
                'user' => null,
                'user_id' => null,
                'username' => $username,
            ];
        }

        if (isset($user['can_login']) && (int)$user['can_login'] !== 1) {
            return [
                'success' => false,
                'reason' => 'login_disabled',
                'user' => null,
                'user_id' => (int) $user['id'],
                'username' => (string) $user['username'],
            ];
        }

        if (empty($user['password'])) {
            return [
                'success' => false,
                'reason' => 'no_password_set',
                'user' => null,
                'user_id' => (int) $user['id'],
                'username' => (string) $user['username'],
            ];
        }

        if (password_verify($password, $user['password'])) {
            return [
                'success' => true,
                'reason' => null,
                'user' => $user,
                'user_id' => (int) $user['id'],
                'username' => (string) $user['username'],
            ];
        }

        return [
            'success' => false,
            'reason' => 'invalid_password',
            'user' => null,
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
        ];
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT u.*, l.name AS location_name, l.address AS location_address, l.city AS location_city FROM users u LEFT JOIN locations l ON u.location_id = l.id WHERE u.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateUser($id, $data) {
        $email = (isset($data['email']) && trim($data['email']) !== '') ? $data['email'] : null;
        $firstName = (isset($data['first_name']) && trim($data['first_name']) !== '') ? $data['first_name'] : null;
        $lastName = (isset($data['last_name']) && trim($data['last_name']) !== '') ? $data['last_name'] : null;

        $fields = ["first_name=?", "last_name=?", "email=?", "username=?", "location_id=?"];
        $params = [$firstName, $lastName, $email, $data['username'], $data['location_id']];

        $canLogin = null;
        if (array_key_exists('can_login', $data)) {
            $canLogin = (int)$data['can_login'] === 1 ? 1 : 0;
            $fields[] = "can_login=?";
            $params[] = $canLogin;

            if ($canLogin === 0) {
                $fields[] = "password=NULL";
            }
        }

        if (($canLogin === null || $canLogin === 1) && !empty($data['password'])) {
            $fields[] = "password=?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (isset($data['role'])) {
            $fields[] = "role=?";
            $params[] = $data['role'];
        }

        $params[] = $id;
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id=?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser($id) {
        // Prüfen, ob der Benutzer noch Assets hat
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM assets WHERE user_id = ?");
        $stmt->execute([$id]);
        $assetCount = $stmt->fetchColumn();

        if ($assetCount > 0) {
            throw new \Exception("Der Benutzer hat noch zugewiesene Assets und kann nicht gelöscht werden.");
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        // Wenn das Passwort aktuell leer ist (nicht eingeloggt war oder Admin es geleert hat),
        // erlauben wir die Änderung ohne alte Verifikation?
        // Normalerweise sollte ein User ein Passwort haben, wenn er sich einloggen darf.
        if (!empty($user['password']) && !password_verify($oldPassword, $user['password'])) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    }

    public function countUsersFiltered($search = '') {
        $query = "SELECT COUNT(*) FROM users u LEFT JOIN locations l ON u.location_id = l.id";
        $params = [];
        if (!empty($search)) {
            $query .= " WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR l.name LIKE ?";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getUsersPaginatedFiltered($search = '', $limit = 20, $offset = 0) {
        $query = "SELECT u.*, l.name as location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id";
        $params = [];
        if (!empty($search)) {
            $query .= " WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR l.name LIKE ?";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        $query .= " ORDER BY u.last_name, u.first_name LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($query);
        
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p, \PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
