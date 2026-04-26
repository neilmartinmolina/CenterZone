<?php
// Structured Error Handling System
class ErrorHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Handle database errors with logging
    public function handleDatabaseError($error, $query = "", $params = []) {
        $errorMessage = "Database Error: " . $error->getMessage();
        
        // Log error to database
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO error_logs (error_message, query, params, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $errorMessage,
                $query,
                json_encode($params)
            ]);
        } catch (Exception $logError) {
            error_log("Failed to log database error: " . $logError->getMessage());
        }
        
        return "A database error occurred. Please try again later.";
    }
    
    // Handle general application errors
    public function handleError($message, $type = "error") {
        $errorMessage = ucfirst($type) . ": " . $message;
        
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO error_logs (error_message, created_at)
                    VALUES (?, NOW())
                ");
                $stmt->execute([$errorMessage]);
            } catch (Exception $logError) {
                error_log("Failed to log error: " . $logError->getMessage());
            }
        }
        
        return $errorMessage;
    }
    
    // Validate input data
    public function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : "";
            
            if (isset($rule["required"]) && $rule["required"] && empty($value)) {
                $errors[$field] = ucfirst($field) . " is required";
                continue;
            }
            
            if (isset($rule["min_length"]) && strlen($value) < $rule["min_length"]) {
                $errors[$field] = ucfirst($field) . " must be at least " . $rule["min_length"] . " characters";
            }
            
            if (isset($rule["max_length"]) && strlen($value) > $rule["max_length"]) {
                $errors[$field] = ucfirst($field) . " must be no more than " . $rule["max_length"] . " characters";
            }
            
            if (isset($rule["pattern"]) && !preg_match($rule["pattern"], $value)) {
                $errors[$field] = $rule["pattern_message"] ?? ucfirst($field) . " format is invalid";
            }
            
            if (isset($rule["type"]) && $rule["type"] === "email" && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Please enter a valid email address";
            }
        }
        
        return $errors;
    }
    
    // Sanitize output
    public function sanitizeOutput($string) {
        return htmlspecialchars($string, ENT_QUOTES, "UTF-8");
    }
}
?>
