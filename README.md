India Pincode Database Setup Guide
This guide helps you set up a MySQL database and import Indian pincode data using the index.php script already placed in your project.
🧰 Prerequisites
Ensure you have:

MySQL server (e.g., XAMPP, MAMP, or a live server)
PHP enabled (PHP 7.4+ recommended)
A .csv file with pincode data
Basic knowledge of phpMyAdmin or terminal/command line

🏗️ Step 1: Create the MySQL Database
Log in to your MySQL server (e.g., via phpMyAdmin or terminal) and run:
CREATE DATABASE IF NOT EXISTS india_pincode;

📂 Step 2: Use the Database
Switch to the database:
USE india_pincode;

🧱 Step 3: Create the pincodes Table
Create the table to store pincode data:
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

📤 Step 4: Prepare the CSV File
Your CSV file must:

Have headers in the first row
Contain 11 columns: circlename, regionname, divisionname, officename, pincode, officetype, delivery, district, statename, latitude, longitude
Use UTF-8 encoding (without BOM)
Use comma (,) as the delimiter

Example row:
"Andhra Pradesh Circle","Vijayawada","Guntur","Guntur H.O","522002","H.O","Delivery","Guntur","Andhra Pradesh","16.5062","80.6480"

Note: The serial field is auto-generated and should not be in the CSV.
🚀 Step 5: Use the PHP Script
Since index.php is already in your project:

Start your web server (e.g., XAMPP).
Navigate to http://localhost/your-folder/index.php.
Upload your CSV file using the form.
Monitor the progress bar and status messages.
Use the "Delete All Records" button to clear the table if needed.

🛠 Troubleshooting

CSV Errors: Ensure the CSV has 11 columns and is saved as UTF-8 (without BOM) using Notepad++ or VS Code.
No Data Inserted: Check error messages on the page and verify CSV column order.
Connection Issues: Ensure MySQL is running and update index.php with correct $username, $password, and $dbname if needed.
Slow Upload: For large CSVs, increase max_execution_time in php.ini.

✅ You're Done!
You now have:

A MySQL database with Indian pincode data
A working PHP uploader for CSV imports

