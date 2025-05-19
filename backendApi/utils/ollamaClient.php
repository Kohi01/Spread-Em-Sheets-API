<?php
class OllamaClient {    
    private $apiUrl;
    private $model;
    
    public function __construct($apiUrl = "http://localhost:11434/api/generate", $model = "llama3") {
        $this->apiUrl = $apiUrl;
        $this->model = $model;
    }
    
    public function summarize($text) {
        $prompt = "Please summarize the following spreadsheet data concisely, highlighting key insights and patterns:\n\n" . $text;
        return $this->generateResponse($prompt);
    }
    
    public function customAnalysis($text, $customPrompt) {
        $prompt = $customPrompt . "\n\nHere is the spreadsheet data to analyze:\n\n" . $text;
        return $this->generateResponse($prompt);
    }
    
    private function generateResponse($prompt) {
        $data = [
            "model" => $this->model,
            "prompt" => $prompt,
            "stream" => false
        ];
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($this->apiUrl, false, $context);
        
        if($result === FALSE) {
            throw new Exception("Failed to connect to Ollama API. Make sure Ollama is running.");
        }
        
        $response = json_decode($result, true);
        
        if(isset($response['response'])) {
            return $response['response'];
        } else {
            throw new Exception("Invalid response from Ollama API.");
        }
    }
}
?>