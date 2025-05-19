<?php   
header("Access-Control-Allow-Origin: *");
// Replace with your Angular app's URL
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600"); // Cache preflight response for 1 hour

/**
 * API Endpoint Router
 *
 * This PHP script serves as a simple API endpoint router, handling GET and POST requests for specific resources.
 * It provides endpoints for spreadsheet processing with Ollama AI integration.
 *
 * Usage:
 * Access the API using the URL pattern: http://localhost/spread-em-sheets/backendApi/(endpoint)
 * 
 * Example endpoints:
 * - http://localhost/spread-em-sheets/backendApi/upload (POST)
 * - http://localhost/spread-em-sheets/backendApi/spreadsheets (GET)
 * - http://localhost/spread-em-sheets/backendApi/spreadsheets/1 (GET)
 * - http://localhost/spread-em-sheets/backendApi/analyze (POST)
 * - http://localhost/spread-em-sheets/backendApi/spreadsheet/1 (DELETE)
 */

    // Include required modules
    require_once "./modules/get.php";
    require_once "./modules/post.php";
    require_once "./config/database.php";
    require './vendor/autoload.php';
    use \Firebase\JWT\JWT;
    
    $con = new Connection();
    $pdo = $con->connect();

    // Initialize Get and Post objects
    $get = new Get($pdo);
    $post = new Post($pdo);

    // Parse the request URI to extract the endpoint
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Extract the part after 'backendApi/'
    $pattern = '/\/spread-em-sheets\/backendApi\/(.*)/';
    if (preg_match($pattern, $request_uri, $matches)) {
        $endpoint = $matches[1];
        // Split the endpoint into segments
        $request = explode('/', $endpoint);
    } else {
        // If the URL doesn't match the expected pattern
        echo json_encode(['error' => 'Invalid API endpoint']);
        http_response_code(404);
        exit;
    }

    // Handle requests based on HTTP method
    switch($_SERVER['REQUEST_METHOD']){

        case 'OPTIONS':
            // Respond to preflight requests
            http_response_code(200);
            exit();
            
        // Handle GET requests
        case 'GET':
            switch($request[0]){
                case 'spreadsheets':
                    // Return JSON-encoded data for all spreadsheets or a specific one
                    if(isset($request[1]) && !empty($request[1])){
                        echo json_encode($get->get_spreadsheet($request[1]));
                    }
                    else{
                        echo json_encode($get->get_spreadsheets());
                    }
                    break;
                
                case 'summary':
                    // Return JSON-encoded data for a specific spreadsheet summary
                    if(isset($request[1]) && !empty($request[1])){
                        echo json_encode($get->get_spreadsheet_summary($request[1]));
                    }
                    else{
                        http_response_code(400);
                        echo json_encode(['error' => 'Spreadsheet ID is required']);
                    }
                    break;
                
                default:
                    // Return a 404 response for unsupported requests
                    echo json_encode(['error' => 'Endpoint not found']);
                    http_response_code(404);
                    break;
            }
            break;
            
        // Handle POST requests    
        case 'POST':
            switch($request[0]){
                
                case 'upload':
                    // Handle file upload for spreadsheet processing
                    if (isset($_FILES['file'])) {
                        $file = $_FILES['file'];
                        
                        // Call the method to handle the spreadsheet upload and processing
                        echo json_encode($post->process_spreadsheet($file));
                    } else {
                        echo json_encode(['status' => 'failed', 'message' => 'No file uploaded or upload error.']);
                    }
                    break;

                case 'analyze':
                    // Process a specific spreadsheet with custom AI prompt
                    $data = json_decode(file_get_contents("php://input"));
                    
                    if (!isset($data->id) || !isset($data->prompt)) {
                        echo json_encode(['status' => 'failed', 'message' => 'Missing spreadsheet ID or prompt.']);
                        break;
                    }
                    
                    $id = filter_var($data->id, FILTER_VALIDATE_INT);
                    $prompt = $data->prompt;
                    
                    if (!$id) {
                        echo json_encode(['status' => 'failed', 'message' => 'Invalid spreadsheet ID.']);
                        break;
                    }
                    
                    echo json_encode($post->analyze_spreadsheet($id, $prompt));
                    break;

                default:
                    // Return a 404 response for unsupported requests
                    echo json_encode(['error' => 'Endpoint not found']);
                    http_response_code(404);
                    break;
            }
            break;
            
        case 'DELETE':
            switch ($request[0]) {
                case 'spreadsheet':
                    // Delete a specific spreadsheet
                    if (isset($request[1]) && !empty($request[1])) {
                        $id = filter_var($request[1], FILTER_VALIDATE_INT);
                        if (!$id) {
                            echo json_encode(['status' => 'failed', 'message' => 'Invalid spreadsheet ID.']);
                            break;
                        }
                        echo json_encode($post->delete_spreadsheet($id));
                    } else {
                        echo json_encode(['status' => 'failed', 'message' => 'Spreadsheet ID must be provided']);
                    }
                    break;
                
                default:
                    // Return a 404 response for unsupported requests
                    echo json_encode(['error' => 'Endpoint not found']);
                    http_response_code(404);
                    break;
            }
            break;
            
        default:
            // Return a 405 response for unsupported HTTP methods
            echo json_encode(['error' => 'Method not allowed']);
            http_response_code(405);
            break;
    }
?>