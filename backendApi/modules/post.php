<?php
class Post {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Helper function to send standardized response
    public function sendPayload($data, $status, $message = "", $code = 200) {
        http_response_code($code);
        return [
            "status" => $status,
            "message" => $message,
            "data" => $data
        ];
    }
    
    // Process uploaded spreadsheet
    public function process_spreadsheet($file) {
        try {
            // Check file type
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
                return $this->sendPayload(null, "failed", "Invalid file type. Only CSV, XLSX, and XLS are supported", 400);
            }
            
            // Parse the spreadsheet
            require_once "./utils/SpreadsheetParser.php";
            $parser = new SpreadsheetParser($file);
            $contentJson = $parser->parse();
            
            // Get text representation for AI
            $textForAI = $parser->getTextForAI();
            
            // Generate summary using Ollama
            require_once "./utils/OllamaClient.php";
            $ollama = new OllamaClient();
            $summary = $ollama->summarize($textForAI);
            
            // Save to database
            $stmt = $this->pdo->prepare("INSERT INTO spreadsheets (filename, upload_date, content_json, summary) VALUES (?, ?, ?, ?)");
            $stmt->execute([$file['name'], date('Y-m-d H:i:s'), $contentJson, $summary]);
            $id = $this->pdo->lastInsertId();
            
            if($id) {
                return $this->sendPayload([
                    "id" => $id,
                    "filename" => $file['name'],
                    "summary" => $summary
                ], "success", "Spreadsheet was uploaded and processed successfully.", 201);
            } else {
                return $this->sendPayload(null, "failed", "Unable to process spreadsheet.", 503);
            }
            
        } catch(Exception $e) {
            return $this->sendPayload(null, "failed", $e->getMessage(), 500);
        }
    }
    
    // Analyze a spreadsheet with a custom prompt
    public function analyze_spreadsheet($id, $prompt) {
        try {
            // Get the spreadsheet data
            $stmt = $this->pdo->prepare("SELECT content_json FROM spreadsheets WHERE id = ?");
            $stmt->execute([$id]);
            $spreadsheet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$spreadsheet) {
                return $this->sendPayload(null, "failed", "Spreadsheet not found.", 404);
            }
            
            // Convert the spreadsheet data to text for AI
            $contentData = json_decode($spreadsheet['content_json'], true);
            $textForAI = "Spreadsheet Data:\n\n";
            
            if(count($contentData) > 0) {
                // Get headers
                $headers = array_keys($contentData[0]);
                $textForAI .= implode("\t", $headers) . "\n";
                
                // Get rows (limit to first 20 rows to avoid overwhelming the AI)
                $rowCount = 0;
                foreach($contentData as $row) {
                    if($rowCount >= 20) {
                        $textForAI .= "... (and " . (count($contentData) - 20) . " more rows)\n";
                        break;
                    }
                    $textForAI .= implode("\t", $row) . "\n";
                    $rowCount++;
                }
                
                // Add some statistics
                $textForAI .= "\nTotal rows: " . count($contentData) . "\n";
                $textForAI .= "Total columns: " . count($headers) . "\n";
            }
            
            // Generate analysis using Ollama with custom prompt
            require_once "./utils/OllamaClient.php";
            $ollama = new OllamaClient();
            $analysis = $ollama->customAnalysis($textForAI, $prompt);
            
            // Save the analysis to the database
            $stmt = $this->pdo->prepare("UPDATE spreadsheets SET last_analysis = ?, last_analysis_date = ? WHERE id = ?");
            $stmt->execute([$analysis, date('Y-m-d H:i:s'), $id]);
            
            return $this->sendPayload([
                "id" => $id,
                "analysis" => $analysis
            ], "success", "Spreadsheet analyzed successfully.");
            
        } catch(Exception $e) {
            return $this->sendPayload(null, "failed", $e->getMessage(), 500);
        }
    }
    
    // Delete a spreadsheet
    public function delete_spreadsheet($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM spreadsheets WHERE id = ?");
            $stmt->execute([$id]);
            
            if($stmt->rowCount() > 0) {
                return $this->sendPayload(null, "success", "Spreadsheet deleted successfully.");
            } else {
                return $this->sendPayload(null, "failed", "Spreadsheet not found.", 404);
            }
        } catch(Exception $e) {
            return $this->sendPayload(null, "failed", $e->getMessage(), 500);
        }
    }
}
?>