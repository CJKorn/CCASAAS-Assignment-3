<?php
require 'vendor/autoload.php';

// Database configuration
$dbconfig = [
    'host' => $_SERVER['RDS_HOSTNAME'],
    'user' => $_SERVER['RDS_USERNAME'],
    'password' => $_SERVER['RDS_PASSWORD'],
    'dbname' => $_SERVER['RDS_DB_NAME'],
    'port' => $_SERVER['RDS_PORT']
];

try {
    // Create database connection
    $pdo = new PDO(
        "mysql:host={$dbconfig['host']};dbname={$dbconfig['dbname']};port={$dbconfig['port']}", 
        $dbconfig['user'], 
        $dbconfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS markdown_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['markdown_file'])) {
    try {
        $file = $_FILES['markdown_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        // Validate file type (optional)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ['text/plain', 'text/markdown'])) {
            throw new Exception('Invalid file type. Please upload a markdown file.');
        }

        // Read file content
        $content = file_get_contents($file['tmp_name']);
        
        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO markdown_files (filename, content) VALUES (?, ?)");
        $stmt->execute([$file['name'], $content]);

        $message = "File uploaded successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all files
$stmt = $pdo->query("SELECT id, filename, upload_date FROM markdown_files ORDER BY upload_date DESC");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// View specific file
$fileContent = null;
$renderedContent = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT content FROM markdown_files WHERE id = ?");
    $stmt->execute([$_GET['view']]);
    $fileContent = $stmt->fetchColumn();
    
    if ($fileContent) {
        // Create Parsedown instance and convert markdown to HTML
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true); // Enable safe mode to prevent XSS
        $renderedContent = $parsedown->text($fileContent);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>Markdown File Manager - AWS Elastic Beanstalk</title>
    <meta name="viewport" content="width=device-width">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css" type="text/css">
</head>
<body>
    <section class="leftSection">
        <h1>LodeStar</h1>
        <p>Blogging Platform Test Application</p>
        
        <!-- Upload Form -->
        <form action="" method="POST" enctype="multipart/form-data">
            <h2>Upload a Markdown File</h2>
            <div class="file-input-wrapper">
                <input type="file" name="markdown_file" accept=".md,.markdown,text/markdown" required>
            </div>
            <button type="submit">Upload</button>
        </form>

        <?php if (isset($message)): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- File List -->
        <div class="file-list">
            <h2>Uploaded Files</h2>
            <?php foreach ($files as $file): ?>
                <a href="?view=<?php echo $file['id']; ?>">
                    <i class="fas fa-file-alt"></i>
                    <?php echo htmlspecialchars($file['filename']); ?>
                    <br>
                    <small style="color: #666; margin-left: 24px;">
                        <?php echo date('F j, Y, g:i a', strtotime($file['upload_date'])); ?>
                    </small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rightSection">
        <?php if ($renderedContent): ?>
            <div class="markdown-content">
                <?php echo $renderedContent; ?>
            </div>
        <?php elseif (!isset($_GET['view'])): ?>
            <div class="welcome-message">
                <h2>Welcome to LodeStar Markdown Viewer</h2>
                <p>Select a file from the left sidebar to view its contents.</p>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>