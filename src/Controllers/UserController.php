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
        return $stmt->execute([
            $data['first_name'], $data['last_name'], $data['email'], 
            $data['username'], $hashedPassword, $data['location_id']
        ]);
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
        // Falls ein neues Passwort eingegeben wurde, hashen. Sonst das alte behalten.
        if (!empty($data['password'])) {
            $sql = "UPDATE users SET first_name=?, last_name=?, email=?, username=?, password=?, location_id=? WHERE id=?";
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            return $stmt->execute([
                $data['first_name'], $data['last_name'], $data['email'], 
                $data['username'], $hashedPassword, $data['location_id'], $id
            ]);
        } else {
            $sql = "UPDATE users SET first_name=?, last_name=?, email=?, username=?, location_id=? WHERE id=?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['first_name'], $data['last_name'], $data['email'], 
                $data['username'], $data['location_id'], $id
            ]);
        }
    }
}
