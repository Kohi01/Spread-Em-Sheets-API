<?php
// This class handles parsing Excel/CSV files
class SpreadsheetParser {
    private $file;
    private $fileType;

    public function __construct($file) {
        $this->file = $file;
        $this->fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
    }

    public function parse() {
        if($this->fileType == 'csv') {
            return $this->parseCSV();
        } elseif($this->fileType == 'xlsx' || $this->fileType == 'xls') {
            return $this->parseExcel();
        } else {
            throw new Exception("Unsupported file type. Please upload CSV, XLS, or XLSX files.");
        }
    }

    private function parseCSV() {
        $data = [];
        if(($handle = fopen($this->file['tmp_name'], "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            while(($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowData = [];
                for($i = 0; $i < count($headers); $i++) {
                    if(isset($row[$i])) {
                        $rowData[$headers[$i]] = $row[$i];
                    } else {
                        $rowData[$headers[$i]] = "";
                    }
                }
                $data[] = $rowData;
            }
            fclose($handle);
        }
        return json_encode($data);
    }

    private function parseExcel() {
        // For Excel files, we need PHPSpreadsheet library
        // This is a simplified version - in a real app, use Composer to install PhpSpreadsheet
        
        // Check if PHPSpreadsheet is available
        if(!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            // If not available, provide instructions
            throw new Exception("PHPSpreadsheet library is required for Excel files. Please install it using Composer.");
        }
        
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($this->file['tmp_name']);
        $spreadsheet = $reader->load($this->file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        
        $data = [];
        $headers = [];
        
        // Get headers from first row
        foreach ($worksheet->getRowIterator(1, 1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(FALSE);
            
            foreach ($cellIterator as $cell) {
                $headers[] = $cell->getValue();
            }
        }
        
        // Get data from remaining rows
        $rowIndex = 0;
        foreach ($worksheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(FALSE);
            
            $rowData = [];
            $colIndex = 0;
            
            foreach ($cellIterator as $cell) {
                if(isset($headers[$colIndex])) {
                    $rowData[$headers[$colIndex]] = $cell->getValue();
                }
                $colIndex++;
            }
            
            $data[] = $rowData;
            $rowIndex++;
        }
        
        return json_encode($data);
    }

    public function getTextForAI() {
        // Convert the parsed data to a text format suitable for AI processing
        $jsonData = json_decode($this->parse(), true);
        $text = "Spreadsheet Data:\n\n";
        
        if(count($jsonData) > 0) {
            // Get headers
            $headers = array_keys($jsonData[0]);
            $text .= implode("\t", $headers) . "\n";
            
            // Get rows (limit to first 20 rows to avoid overwhelming the AI)
            $rowCount = 0;
            foreach($jsonData as $row) {
                if($rowCount >= 20) {
                    $text .= "... (and " . (count($jsonData) - 20) . " more rows)\n";
                    break;
                }
                $text .= implode("\t", $row) . "\n";
                $rowCount++;
            }
            
            // Add some statistics
            $text .= "\nTotal rows: " . count($jsonData) . "\n";
            $text .= "Total columns: " . count($headers) . "\n";
        }
        
        return $text;
    }
}
?>