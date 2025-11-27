<?php
// Security configuration
$VALID_COOKIES = [
    'auth' => 'toot' // Change this to a strong random value
];
$ALLOWED_USERS = [
    'admin' => password_hash('toot', PASSWORD_DEFAULT) // Change password
];

// Base server path
$BASE_PATH = '/srv/lspro-wp/';

// Session and cookie validation
session_start();

// Check if user is authenticated via cookie
function isAuthenticated() {
    global $VALID_COOKIES;
    
    // Check session first
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return true;
    }
    
    // Check cookies
    foreach ($VALID_COOKIES as $cookieName => $expectedValue) {
        if (isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] === $expectedValue) {
            $_SESSION['authenticated'] = true;
            return true;
        }
    }
    
    return false;
}

// Login handler
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($ALLOWED_USERS[$username]) && password_verify($password, $ALLOWED_USERS[$username])) {
        $_SESSION['authenticated'] = true;
        header("Location: ?" . $_SERVER['QUERY_STRING']);
        exit;
    } else {
        $loginError = "Invalid credentials";
    }
}

// Show login form if not authenticated
if (!isAuthenticated()) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login Required</title>
    </head>
    <body>
        <h2>Login Required</h2>
        <?php if (isset($loginError)) echo "<p style='color:red;'>$loginError</p>"; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit" name="login">Login</button>
        </form>
        
        <h3>cURL Upload Instructions:</h3>
        <pre>
# Method 1: Using cookie authentication
curl -X POST \
  -F "file=@/path/to/your/file.txt" \
  -F "path=." \
  -b "auth=your_secret_cookie_value_here" \
  "http://yourserver.com/this_script.php"

# Download from GitHub and set permissions
curl -X POST \
  -F "github_url=https://raw.githubusercontent.com/user/repo/main/file.php" \
  -F "target_path=." \
  -F "permissions=644" \
  -b "auth=your_secret_cookie_value_here" \
  "http://yourserver.com/this_script.php"
        </pre>
    </body>
    </html>
    <?php
    exit;
}

$path = isset($_GET['path']) ? $_GET['path'] : '.';
$fullPath = realpath($BASE_PATH . $path);

// Ensure we don't go outside base path
if (strpos($fullPath, $BASE_PATH) !== 0) {
    $fullPath = $BASE_PATH;
}

// Handle delete
if (isset($_GET['delete'])) {
    $target = $BASE_PATH . $_GET['delete'];
    if (is_file($target)) {
        unlink($target);
    } elseif (is_dir($target)) {
        rmdir($target); // only works on empty dirs
    }
    header("Location: ?path=" . urlencode(dirname($_GET['delete'])));
    exit;
}

// File editor
if (isset($_GET['edit'])) {
    $editFile = $BASE_PATH . $_GET['edit'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = @fopen($editFile, 'w');
        if ($f) {
            fwrite($f, $_POST['content']);
            fclose($f);
            
            // Set permissions if provided
            if (isset($_POST['permissions']) && !empty($_POST['permissions'])) {
                $perms = octdec($_POST['permissions']);
                chmod($editFile, $perms);
            }
            
            echo "<p>Saved.</p><a href='?path=" . urlencode(dirname($_GET['edit'])) . "'>Back</a>";
        } else {
            echo "<p>Failed to save file.</p>";
        }
        exit;
    }
    $content = @file_get_contents($editFile);
    $currentPerms = file_exists($editFile) ? substr(sprintf('%o', fileperms($editFile)), -4) : '0644';
    $data = htmlspecialchars($content ? $content : '', ENT_QUOTES, 'UTF-8');
    echo "<h2>Editing: {$_GET['edit']}</h2>
    <form method='post'>
    <textarea name='content' rows='25' cols='100'>$data</textarea><br>
    <label>Permissions: <input type='text' name='permissions' value='$currentPerms' placeholder='e.g., 644'></label><br>
    <button type='submit'>Save</button>
    </form>";
    exit;
}

// Handle GitHub download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['github_url'])) {
    $githubUrl = $_POST['github_url'];
    $targetPath = $_POST['target_path'] ?? '.';
    $permissions = $_POST['permissions'] ?? '644';
    
    $fullTargetPath = realpath($BASE_PATH . $targetPath) ?: $BASE_PATH . $targetPath;
    
    // Ensure target is within base path
    if (strpos($fullTargetPath, $BASE_PATH) !== 0) {
        $fullTargetPath = $BASE_PATH;
    }
    
    // Extract filename from GitHub URL
    $filename = basename(parse_url($githubUrl, PHP_URL_PATH));
    if (empty($filename)) {
        $filename = 'downloaded_file.php';
    }
    
    $finalPath = $fullTargetPath . '/' . $filename;
    
    // Download from GitHub
    $fileContent = file_get_contents($githubUrl);
    
    if ($fileContent !== false) {
        if (file_put_contents($finalPath, $fileContent) !== false) {
            // Set permissions
            $perms = octdec($permissions);
            chmod($finalPath, $perms);
            
            $result = [
                'status' => 'success',
                'message' => 'File downloaded from GitHub successfully',
                'path' => $finalPath,
                'permissions' => $permissions
            ];
        } else {
            $result = [
                'status' => 'error',
                'message' => 'Failed to write file to server'
            ];
        }
    } else {
        $result = [
            'status' => 'error',
            'message' => 'Failed to download from GitHub URL'
        ];
    }
    
    // Return JSON for cURL or display for browser
    if (isCurlRequest()) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    } else {
        $githubMessage = $result['message'];
    }
}

// Enhanced Upload handler - supports both browser and cURL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['upload']) || isset($_FILES['file']))) {
    // Determine the file input name (browser uses 'upload', cURL can use 'file')
    $fileInput = isset($_FILES['upload']) ? 'upload' : 'file';
    
    // Get destination path from POST or GET
    $uploadPath = isset($_POST['path']) ? $_POST['path'] : $path;
    $fullUploadPath = realpath($BASE_PATH . $uploadPath) ?: $BASE_PATH . $uploadPath;
    
    // Ensure within base path
    if (strpos($fullUploadPath, $BASE_PATH) !== 0) {
        $fullUploadPath = $BASE_PATH;
    }
    
    $uploadedFile = $_FILES[$fileInput];
    $permissions = $_POST['permissions'] ?? '644';
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $targetPath = $fullUploadPath . '/' . basename($uploadedFile['name']);
        
        if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            // Set permissions
            $perms = octdec($permissions);
            chmod($targetPath, $perms);
            
            // If this is a cURL request, return JSON response
            if (isCurlRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'File uploaded successfully',
                    'path' => $targetPath,
                    'permissions' => $permissions
                ]);
                exit;
            } else {
                // Browser request - redirect
                header("Location: ?path=" . urlencode($path));
                exit;
            }
        } else {
            $errorMsg = "Failed to move uploaded file";
        }
    } else {
        $errorMsg = "Upload error: " . $uploadedFile['error'];
    }
    
    // Handle errors for cURL requests
    if (isCurlRequest()) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $errorMsg
        ]);
        exit;
    }
}

// Check if request is likely from cURL
function isCurlRequest() {
    return (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'curl') !== false) ||
           (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
           (isset($_POST['path']) && !isset($_GET['path'])); // POST has path but GET doesn't
}

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    foreach ($VALID_COOKIES as $cookieName => $value) {
        setcookie($cookieName, '', time() - 3600, '/');
    }
    header("Location: ?");
    exit;
}

echo "<h2>Browsing: $fullPath</h2>";
echo "<p><a href='?logout=1'>Logout</a></p>";

// GitHub Download Form
echo "<h3>Download from GitHub</h3>";
if (isset($githubMessage)) {
    echo "<p style='color: " . (strpos($githubMessage, 'success') !== false ? 'green' : 'red') . ";'>$githubMessage</p>";
}
echo "<form method='post'>
    <input type='url' name='github_url' placeholder='https://raw.githubusercontent.com/...' required style='width: 400px;'><br>
    <input type='text' name='target_path' placeholder='Target directory (relative to base)' value='$path' style='width: 200px;'><br>
    <input type='text' name='permissions' placeholder='Permissions (e.g., 644, 755)' value='644' style='width: 100px;'><br>
    <button type='submit'>Download from GitHub</button>
</form>";

// File Upload Form
echo "<h3>Upload File</h3>";
echo "<form method='post' enctype='multipart/form-data'>
    <input type='file' name='upload'><br>
    <input type='text' name='permissions' placeholder='Permissions (e.g., 644, 755)' value='644' style='width: 100px;'><br>
    <button type='submit'>Upload</button>
</form>";

echo "<h3>cURL Examples:</h3>";
echo "<pre>
# Upload local file with permissions:
curl -X POST \\
  -F \"file=@localfile.php\" \\
  -F \"path=.\" \\
  -F \"permissions=755\" \\
  -b \"auth=your_secret_cookie_value_here\" \\
  \"" . $_SERVER['PHP_SELF'] . "\"

# Download from GitHub:
curl -X POST \\
  -F \"github_url=https://raw.githubusercontent.com/user/repo/main/file.php\" \\
  -F \"target_path=wp-content/plugins\" \\
  -F \"permissions=644\" \\
  -b \"auth=your_secret_cookie_value_here\" \\
  \"" . $_SERVER['PHP_SELF'] . "\"

# Download shell script and make executable:
curl -X POST \\
  -F \"github_url=https://raw.githubusercontent.com/user/repo/main/script.sh\" \\
  -F \"target_path=.\" \\
  -F \"permissions=755\" \\
  -b \"auth=your_secret_cookie_value_here\" \\
  \"" . $_SERVER['PHP_SELF'] . "\"
</pre>";

echo "<ul>";
if ($handle = opendir($fullPath)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.' || $entry === '..') continue;
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $entry;
        $relativePath = str_replace($BASE_PATH, '', $filePath);
        $urlPath = urlencode($relativePath);
        $filePerms = substr(sprintf('%o', fileperms($filePath)), -4);
        $delLink = "<a href='?delete=$urlPath' onclick=\"return confirm('Delete $entry?')\">delete</a>";
        if (is_dir($filePath)) {
            echo "<li>[<a href='?path=$urlPath'>$entry</a>] (dir) - $delLink</li>";
        } else {
            echo "<li>$entry (perm: $filePerms) - <a href='?edit=$urlPath'>edit</a> | $delLink</li>";
        }
    }
    closedir($handle);
}
echo "</ul>";
?>
