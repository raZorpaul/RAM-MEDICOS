<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly, return in JSON
ini_set('log_errors', 1);

try {
    // Log that the script is starting
    error_log("Admin login attempt started");
    
    // Check required files
    if (!file_exists("../config/cors-headers.php")) {
        throw new Exception("cors-headers.php file not found");
    }
    if (!file_exists("../config/db_connect.php")) {
        throw new Exception("db_connect.php file not found");
    }
    
    require_once("../config/cors-headers.php");
    header('Content-Type: application/json');
    
    require_once("../config/db_connect.php"); // include DB connection
    
    // Check if database connection exists
    if (!isset($conn) || $conn === null) {
        throw new Exception("Database connection failed: \$conn is not set");
    }
    
    // Check for database connection errors
    if ($conn->connect_error) {
        throw new Exception("Database connection error: " . $conn->connect_error);
    }
    
    $response = [];
    
    // Log POST data (without password for security)
    error_log("POST data received: mobile_no=" . (isset($_POST['mobile_no']) ? $_POST['mobile_no'] : 'NOT SET') . ", password=" . (isset($_POST['password']) ? 'SET' : 'NOT SET'));
    
    // ✅ Step 1: Check if mobile_no and password are provided
    if (isset($_POST['mobile_no']) && isset($_POST['password'])) {
        $mobile_no = trim($_POST['mobile_no']);
        $password = $_POST['password'];
        
        error_log("Processing admin login for mobile: $mobile_no");
        
        // ✅ Step 2: Find admin by mobile_no
        $sql = "SELECT * FROM users WHERE mobile_no = ? AND role = 'admin' LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("SQL prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $mobile_no);
        $stmt->execute();
        
        if ($stmt->error) {
            throw new Exception("SQL execute error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            error_log("Admin found with user_id: " . $admin['user_id']);
            
            // ✅ Step 3: Verify password
            if (password_verify($password, $admin['password'])) {
                error_log("Password verified successfully");
                
                // ✅ Step 4: Generate OTP (6 digits)
                $otp = rand(100000, 999999);
                $expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));
                error_log("Generated OTP for $mobile_no: $otp"); // Log OTP for testing
                
                // ✅ Step 5: Remove any previous OTPs for this mobile number
                $delete_sql = "DELETE FROM otps WHERE mobile_no = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                
                if ($delete_stmt === false) {
                    throw new Exception("DELETE SQL prepare failed: " . $conn->error);
                }
                
                $delete_stmt->bind_param("s", $mobile_no);
                $delete_stmt->execute();
                
                if ($delete_stmt->error) {
                    error_log("Warning: Delete OTP error: " . $delete_stmt->error);
                }
                
                // ✅ Step 6: Store new OTP
                $insert_sql = "INSERT INTO otps (user_id, mobile_no, otp_code, expires_at) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if ($insert_stmt === false) {
                    throw new Exception("INSERT SQL prepare failed: " . $conn->error);
                }
                
                $insert_stmt->bind_param("isss", $admin['user_id'], $mobile_no, $otp, $expires_at);
                $insert_stmt->execute();
                
                if ($insert_stmt->error) {
                    throw new Exception("INSERT SQL execute error: " . $insert_stmt->error);
                }
                
                // ✅ Step 7: (Simulate) Send OTP via SMS (for now, just return in response)
                // In real app: use an API like Fast2SMS, Twilio, etc.
                $response = [
                    "status" => "success",
                    "message" => "OTP sent successfully",
                    "otp" => $otp, // Include OTP in response for testing/debugging
                    "otp_message" => "Your OTP is: $otp (for testing only - not sent via SMS)",
                    "mobile_no" => $mobile_no
                ];
                
                error_log("Admin login successful, OTP generated");
            } else {
                error_log("Password verification failed");
                $response = ["status" => "error", "message" => "Invalid password"];
            }
        } else {
            error_log("Admin not found with mobile: $mobile_no");
            $response = ["status" => "error", "message" => "Admin not found or not authorized"];
        }
        
    } else {
        $missing = [];
        if (!isset($_POST['mobile_no'])) $missing[] = "mobile_no";
        if (!isset($_POST['password'])) $missing[] = "password";
        error_log("Missing required fields: " . implode(", ", $missing));
        $response = ["status" => "error", "message" => "Missing required fields: " . implode(", ", $missing)];
    }
    
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $response = [
        "status" => "error",
        "message" => "Server error: " . $e->getMessage(),
        "error_type" => get_class($e),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ];
    http_response_code(500);
} catch (Error $e) {
    error_log("Admin login fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $response = [
        "status" => "error",
        "message" => "Fatal error: " . $e->getMessage(),
        "error_type" => get_class($e),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ];
    http_response_code(500);
}

// ✅ Output response as JSON
echo json_encode($response);
?>