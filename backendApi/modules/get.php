<?php
class Get {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Helper function to send standardized response
    private function sendPayload($data, $status, $message = "", $code = 200) {
        return [
            "status" => $status,
            "message" => $message,
            "data" => $data
        ];
    }
    
    // Get all spreadsheets (without content_json to reduce response size)
    public function get_spreadsheets() {
        try {
            $stmt = $this->pdo->query("SELECT id, filename, upload_date, summary FROM spreadsheets ORDER BY upload_date DESC");
            $spreadsheets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if(count($spreadsheets) > 0) {
                return $this->sendPayload($spreadsheets, "success", "Spreadsheets retrieved successfully.");
            } else {
                return $this->sendPayload([], "success", "No spreadsheets found.");
            }
        } catch(PDOException $e) {
            return $this->sendPayload(null, "failed", "Database error: " . $e->getMessage(), 500);
        }
    }
    
    // Get specific spreadsheet by ID
    public function get_spreadsheet($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM spreadsheets WHERE id = ?");
            $stmt->execute([$id]);
            $spreadsheet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($spreadsheet) {
                // Convert stored JSON back to array
                $spreadsheet['content_data'] = json_decode($spreadsheet['content_json'], true);
                unset($spreadsheet['content_json']); // Remove the raw JSON
                
                return $this->sendPayload($spreadsheet, "success", "Spreadsheet retrieved successfully.");
            } else {
                return $this->sendPayload(null, "failed", "Spreadsheet not found.", 404);
            }
        } catch(PDOException $e) {
            return $this->sendPayload(null, "failed", "Database error: " . $e->getMessage(), 500);
        }
    }
    
    // Get only the summary of a specific spreadsheet
    public function get_spreadsheet_summary($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, filename, upload_date, summary FROM spreadsheets WHERE id = ?");
            $stmt->execute([$id]);
            $spreadsheet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($spreadsheet) {
                return $this->sendPayload($spreadsheet, "success", "Summary retrieved successfully.");
            } else {
                return $this->sendPayload(null, "failed", "Spreadsheet not found.", 404);
            }
        } catch(PDOException $e) {
            return $this->sendPayload(null, "failed", "Database error: " . $e->getMessage(), 500);
        }
    }
}
?>