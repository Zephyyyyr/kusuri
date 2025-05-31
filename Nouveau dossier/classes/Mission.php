<?php
class Mission {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createMission($userId, $title, $description, $priority) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO missions (user_id, title, description, priority) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$userId, $title, $description, $priority]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getMissionsByUser($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM missions WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getAllMissions() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, u.username 
                FROM missions m 
                JOIN users u ON m.user_id = u.id 
                ORDER BY m.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function updateMissionStatus($missionId, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE missions SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $missionId]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function addComment($missionId, $userId, $comment) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO mission_comments (mission_id, user_id, comment) VALUES (?, ?, ?)");
            return $stmt->execute([$missionId, $userId, $comment]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getMissionComments($missionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mc.*, u.username 
                FROM mission_comments mc 
                JOIN users u ON mc.user_id = u.id 
                WHERE mc.mission_id = ? 
                ORDER BY mc.created_at ASC
            ");
            $stmt->execute([$missionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
}
?> 