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

    public function createUser($data) {
        $sql = "INSERT INTO users (first_name, last_name, email, username, password, location_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $email = (isset($data['email']) && trim($data['email']) !== '') ? $data['email'] : null;
        $firstName = (isset($data['first_name']) && trim($data['first_name']) !== '') ? $data['first_name'] : null;
        $lastName = (isset($data['last_name']) && trim($data['last_name']) !== '') ? $data['last_name'] : null;

        $stmt->bindValue(1, $firstName, $firstName !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(2, $lastName, $lastName !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(3, $email, $email !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(4, $data['username'], \PDO::PARAM_STR);
        $stmt->bindValue(5, $hashedPassword, \PDO::PARAM_STR);
        $stmt->bindValue(6, $data['location_id'], $data['location_id'] !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);

        return $stmt->execute();
    }

    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateUser($id, $data) {
        $email = (isset($data['email']) && trim($data['email']) !== '') ? $data['email'] : null;
        $firstName = (isset($data['first_name']) && trim($data['first_name']) !== '') ? $data['first_name'] : null;
        $lastName = (isset($data['last_name']) && trim($data['last_name']) !== '') ? $data['last_name'] : null;

        $fields = ["first_name=?", "last_name=?", "email=?", "username=?", "location_id=?"];
        $params = [$firstName, $lastName, $email, $data['username'], $data['location_id']];

        if (!empty($data['password'])) {
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
}
