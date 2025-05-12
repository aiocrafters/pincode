🇮🇳 India Pincode Database Setup and CSV Import Guide
This guide walks you through creating a MySQL database and importing Indian pincode data from a CSV file using a PHP script.
🧰 Prerequisites
Before you begin, ensure you have:

MySQL server (e.g., XAMPP, MAMP, or a live server)
PHP enabled (PHP 7+ recommended)
A .csv file containing pincode data
Basic knowledge of phpMyAdmin or terminal/command line

🏗️ Step 1: Create the MySQL Database
Log in to your MySQL server and execute:
CREATE DATABASE IF NOT EXISTS india_pincode;

📂 Step 2: Use the Database
Switch to the newly created database:
USE india_pincode;

🧱 Step 3: Create the pincodes Table
Create the pincodes table to hold the data:
CREATE TABLE IF NOT EXISTS pincodes (
    serial INT AUTO_INCREMENT PRIMARY KEY,
    circlename VARCHAR(100),
    regionname VARCHAR(100),
    divisionname VARCHAR(100),
    officename VARCHAR(150),
    pincode VARCHAR(10),
    officetype VARCHAR(50),
    delivery VARCHAR(10),
    district VARCHAR(100),
    statename VARCHAR(100),
    latitude DECIMAL(10, 6),
    longitude DECIMAL(10, 6)
);

💡 Step 4: Table Field Descriptions



Column Name
Data Type
Description



serial
INT (AUTO_INCREMENT)
Unique record ID


circlename
VARCHAR(100)
Postal circle name


regionname
VARCHAR(100)
Region of the post office


divisionname
VARCHAR(100)
Postal division


officename
VARCHAR(150)
Post office name


pincode
VARCHAR(10)
PIN code


officetype
VARCHAR(50)
Type of post office


delivery
VARCHAR(10)
Delivery status


district
VARCHAR(100)
District name


statename
VARCHAR(100)
State name


latitude
DECIMAL(10, 6)
Latitude coordinate (optional)


longitude
DECIMAL(10, 6)
Longitude coordinate (optional)


📤 Step 5: Prepare the CSV File
Ensure your CSV file:

Has headers in the first row
Has 12 columns in the correct order
Uses UTF-8 encoding
Uses comma (,) or tab (\t) as the delimiter

🌐 Step 6: Create the PHP Upload Script
Create a file called index.php in your project directory and paste the following code:
<!DOCTYPE html>
<html>
<head>
    <title>Upload Indian Pincode CSV</title>
    <style>
        .progress-container {
            width: 100%;
            background-color: #f3f3f3;
            margin-top: 20px;
        }
        .progress-bar {
            width: 0%;
            height: 30px;
            background-color: #4CAF50;
            text-align: center;
            line-height: 30px;
            color: white;
            transition: width 0.3s;
        }
        .status {
            margin-top: 10px;
            font-weight: bold;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2>Upload Indian Pincode CSV</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <br><br>
        <input type="submit" name="submit" value="Upload CSV">
    </form>
    <form method="POST" onsubmit="return confirm('Are you sure you want to delete all records in the pincodes table? This action cannot be undone.');">
        <br>
        <input type="submit" name="truncate" value="Delete All Records" style="background-color: #f44336; color: white; border: none; padding: 10px 20px; cursor: pointer;">
    </form>

    <div class="progress-container" id="progressContainer" style="display:none;">
        <div class="progress-bar" id="progressBar">0%</div>
    </div>
    <div class="status" id="status"></div>
    <div id="errorLog"></div>
    <div id="successMessage"></div>

    <?php
    // Database configuration
    $host = "localhost";
    $username = "root";
    $password = "";
    $dbname = "india_pincode";

    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Create connection
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("<div class='error'>Connection failed: " . $conn->connect_error . "</div>");
    }

    // Handle truncate request
    if (isset($_POST['truncate'])) {
        try {
            $sql = "TRUNCATE TABLE pincodes";
            if ($conn->query($sql)) {
                echo '<div class="success">All records deleted successfully!</div>';
                echo '<script>document.getElementById("status").innerHTML = "Table truncated. Total records in database: 0";</script>';
            } else {
                echo '<div class="error">Error truncating table: ' . $conn->error . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . $e->getMessage() . '</div>';
        }
    }

    // Handle CSV upload
    if (isset($_POST['submit'])) {
        // Check if file was uploaded
        if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            echo '<script>
                document.getElementById("progressContainer").style.display = "block";
            </script>';
            ob_flush();
            flush();

            // Initialize counters
            $row = 0;
            $successCount = 0;
            $errorCount = 0;
            $totalRows = 0;
            $errors = [];

            // First pass to count rows
            $file = fopen($_FILES['csv_file']['tmp_name'], "r");
            while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
                if (count($data) > 1) { // Skip empty lines
                    $totalRows++;
                }
            }
            $totalRows--; // Subtract header row
            rewind($file);

            // Start transaction
            $conn->begin_transaction();

            try {
                while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
                    if ($row == 0) { // Skip header row
                        $row++;
                        continue;
                    }

                    // Update progress
                    $progress = round(($row / $totalRows) * 100);
                    echo '<script>
                        document.getElementById("progressBar").style.width = "'.$progress.'%";
                        document.getElementById("progressBar").innerHTML = "'.$progress.'%";
                        document.getElementById("status").innerHTML = "Processing row '.$row.' of '.$totalRows.'";
                    </script>';
                    ob_flush();
                    flush();

                    // Validate data (expecting 11 columns in CSV)
                    if (count($data) < 11) {
                        $errors[] = "Row $row: Skipped - Only ".count($data)." columns found (need 11)";
                        $errorCount++;
                        $row++;
                        continue;
                    }

                    // Process data (CSV has 11 columns matching DB columns 2-12)
                    $circlename   = $conn->real_escape_string(trim($data[0] ?? ''));  // CSV col 1 -> DB col 2
                    $regionname   = $conn->real_escape_string(trim($data[1] ?? ''));  // CSV col 2 -> DB col 3
                    $divisionname = $conn->real_escape_string(trim($data[2] ?? ''));  // CSV col 3 -> DB col 4
                    $officename   = $conn->real_escape_string(trim($data[3] ?? ''));  // CSV col 4 -> DB col 5
                    $pincode      = is_numeric($data[4] ?? 0) ? (int)$data[4] : 0;    // CSV col 5 -> DB col 6
                    $officetype   = $conn->real_escape_string(trim($data[5] ?? ''));  // CSV col 6 -> DB col 7
                    $delivery     = $conn->real_escape_string(trim($data[6] ?? ''));  // CSV col 7 -> DB col 8
                    $district     = $conn->real_escape_string(trim($data[7] ?? ''));  // CSV col 8 -> DB col 9
                    $statename    = $conn->real_escape_string(trim($data[8] ?? ''));  // CSV col 9 -> DB col 10
                    $latitude     = is_numeric($data[9] ?? 0) ? (float)$data[9] : 0;  // CSV col 10 -> DB col 11
                    $longitude    = is_numeric($data[10] ?? 0) ? (float)$data[10] : 0;// CSV col 11 -> DB col 12

                    // Insert into database
                    $sql = "INSERT INTO pincodes 
                        (circlename, regionname, divisionname, officename, pincode, officetype, delivery, district, statename, latitude, longitude) 
                        VALUES 
                        ('$circlename', '$regionname', '$divisionname', '$officename', $pincode, '$officetype', '$delivery', '$district', '$statename', $latitude, $longitude)";

                    if ($conn->query($sql)) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $row: " . $conn->error;
                        $errorCount++;
                    }

                    $row++;
                }

                // Commit transaction
                $conn->commit();

                // Final output
                echo '<script>
                    document.getElementById("progressBar").style.width = "100%";
                    document.getElementById("progressBar").innerHTML = "100%";
                    document.getElementById("status").innerHTML = "Processing complete!";
                    document.getElementById("successMessage").innerHTML = "<div class=\"success\">Successfully imported '.$successCount.' of '.$totalRows.' records!</div>";
                </script>';

                // Show errors if any
                if ($errorCount > 0) {
                    echo '<script>
                        document.getElementById("errorLog").innerHTML = "<div class=\"error\"><strong>Errors ('.$errorCount.'):</strong><br>'.implode("<br>", array_slice($errors, 0, 10)).'" + 
                        "'.(count($errors) > 10 ? '<br>... and '.(count($errors)-10).' more' : '').'</div>";
                    </script>';
                }

            } catch (Exception $e) {
                $conn->rollback();
                echo '<script>
                    document.getElementById("status").innerHTML = "Error: '.str_replace('"', '\"', $e->getMessage()).'";
                    document.getElementById("progressBar").style.backgroundColor = "#f44336";
                </script>';
            }

            fclose($file);

            // Verify final count
            $result = $conn->query("SELECT COUNT(*) as count FROM pincodes");
            if ($result) {
                $count = $result->fetch_assoc()['count'];
                echo '<script>
                    document.getElementById("status").innerHTML += "<br>Total records in database: '.$count.'";
                </script>';
            }
        } else {
            echo "<div class='error'>Error: Please upload a valid CSV file.</div>";
        }
    }

    $conn->close();
    ?>
</body>
</html>

🚀 Final Step: Test the Upload

Start your local web server (e.g., XAMPP).
Navigate to http://localhost/your-folder/index.php.
Upload your CSV file.
View success and debug messages on the screen.

🛠 Troubleshooting

Ensure your CSV has exactly 12 fields.
Try saving the CSV as UTF-8 (without BOM) using Excel or Notepad++.
If nothing inserts, verify field mappings and column order.

✅ You're Done!
You now have:

A MySQL database for Indian pincodes
A PHP-based uploader to import CSV data

