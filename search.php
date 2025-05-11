<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $host = "localhost";
    $username = "root";
    $password = "";
    $dbname = "india_pincode";

    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        echo '<option value="">Database connection error</option>';
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'get_districts' && isset($_POST['state'])) {
        $state = $conn->real_escape_string(trim($_POST['state']));
        $sql = "SELECT DISTINCT district FROM pincodes WHERE UPPER(statename) = UPPER('$state') ORDER BY district";
        error_log("District query: $sql");
        $result = $conn->query($sql);
        if (!$result) {
            error_log("District query failed: " . $conn->error);
            echo '<option value="">Error fetching districts</option>';
            $conn->close();
            exit;
        }
        echo '<option value="">Select District</option>';
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($row['district']) . "'>" . htmlspecialchars($row['district']) . "</option>";
        }
        $conn->close();
        exit;
    }

    if ($action === 'get_offices' && isset($_POST['state'], $_POST['district'])) {
        $state = $conn->real_escape_string(trim($_POST['state']));
        $district = $conn->real_escape_string(trim($_POST['district']));
        $sql = "SELECT DISTINCT officename FROM pincodes WHERE UPPER(statename) = UPPER('$state') AND UPPER(district) = UPPER('$district') ORDER BY officename";
        error_log("Office query: $sql");
        $result = $conn->query($sql);
        if (!$result) {
            error_log("Office query failed: " . $conn->error);
            echo '<option value="">Error fetching offices</option>';
            $conn->close();
            exit;
        }
        echo '<option value="">Select Office Name</option>';
        if ($result->num_rows > 0) {
            error_log("Found " . $result->num_rows . " offices for State: $state, District: $district");
            while ($row = $result->fetch_assoc()) {
                echo "<option value='" . htmlspecialchars($row['officename']) . "'>" . htmlspecialchars($row['officename']) . "</option>";
            }
        } else {
            error_log("No offices found for State: $state, District: $district");
        }
        $conn->close();
        exit;
    }

    if ($action === 'get_details' && isset($_POST['state'], $_POST['district'], $_POST['officename'])) {
        $state = $conn->real_escape_string(trim($_POST['state']));
        $district = $conn->real_escape_string(trim($_POST['district']));
        $officename = $conn->real_escape_string(trim($_POST['officename']));
        $sql = "SELECT statename, district, officename, pincode, circlename, regionname, divisionname, officetype, delivery, latitude, longitude 
                FROM pincodes 
                WHERE UPPER(statename) = UPPER('$state') AND UPPER(district) = UPPER('$district') AND UPPER(officename) = UPPER('$officename')";
        error_log("Details query: $sql");
        $result = $conn->query($sql);
        if (!$result) {
            error_log("Details query failed: " . $conn->error);
            $conn->close();
            exit;
        }
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>" . htmlspecialchars($row['statename']) . "</td>
                <td>" . htmlspecialchars($row['district']) . "</td>
                <td>" . htmlspecialchars($row['officename']) . "</td>
                <td>" . htmlspecialchars($row['pincode']) . "</td>
                <td>" . htmlspecialchars($row['circlename']) . "</td>
                <td>" . htmlspecialchars($row['regionname']) . "</td>
                <td>" . htmlspecialchars($row['divisionname']) . "</td>
                <td>" . htmlspecialchars($row['officetype']) . "</td>
                <td>" . htmlspecialchars($row['delivery']) . "</td>
                <td>" . ($row['latitude'] ? htmlspecialchars($row['latitude']) : '-') . "</td>
                <td>" . ($row['longitude'] ? htmlspecialchars($row['longitude']) : '-') . "</td>
            </tr>";
        }
        $conn->close();
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pincode Search</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h2 {
            color: #333;
        }
        .search-container {
            margin-bottom: 20px;
        }
        select {
            padding: 10px;
            margin: 10px 0;
            width: 200px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        table {
            width: 100%;
            max-width: 800px;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .error {
            color: red;
        }
        .no-results {
            color: #555;
            font-style: italic;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>if (typeof jQuery == 'undefined') alert('jQuery not loaded');</script>
</head>
<body>
    <h2>Pincode Search</h2>
    <div class="search-container">
        <div>
            <label for="state">State:</label><br>
            <select id="state" name="state">
                <option value="">Select State</option>
                <?php
                $host = "localhost";
                $username = "root";
                $password = "";
                $dbname = "india_pincode";

                $conn = new mysqli($host, $username, $password, $dbname);
                if ($conn->connect_error) {
                    die("<div class='error'>Connection failed: " . $conn->connect_error . "</div>");
                }

                $sql = "SELECT DISTINCT statename FROM pincodes ORDER BY statename";
                $result = $conn->query($sql);
                while ($row = $result->fetch_assoc()) {
                    echo "<option value='" . htmlspecialchars($row['statename']) . "'>" . htmlspecialchars($row['statename']) . "</option>";
                }
                $conn->close();
                ?>
            </select>
        </div>
        <div>
            <label for="district">District:</label><br>
            <select id="district" name="district" disabled>
                <option value="">Select District</option>
            </select>
        </div>
        <div>
            <label for="officename">Office Name:</label><br>
            <select id="officename" name="officename" disabled>
                <option value="">Select Office Name</option>
            </select>
        </div>
    </div>

    <div id="results">
        <table id="pincodeTable" style="display: none;">
            <thead>
                <tr>
                    <th>State</th>
                    <th>District</th>
                    <th>Office</th>
                    <th>Pincode</th>
                    <th>Circle</th>
                    <th>Region</th>
                    <th>Division</th>
                    <th>Office Type</th>
                    <th>Delivery</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
        <div id="noResults" class="no-results"></div>
    </div>

    <script>
        $(document).ready(function() {
            $('#state').change(function() {
                var state = $.trim($(this).val());
                console.log('State changed: ' + state);
                if (state) {
                    console.log('Fetching districts for State: ' + state);
                    $.ajax({
                        url: 'search.php',
                        type: 'POST',
                        data: { state: state, action: 'get_districts' },
                        success: function(data) {
                            console.log('Districts received: ' + data);
                            $('#district').html(data).prop('disabled', false);
                            $('#officename').html('<option value="">Select Office Name</option>').prop('disabled', true);
                            $('#pincodeTable').hide();
                            $('#noResults').text('');
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error: ' + error);
                            console.error('Status: ' + status);
                            console.error('Response: ' + xhr.responseText);
                            alert('Error fetching districts.');
                        }
                    });
                } else {
                    console.log('No state selected, resetting districts.');
                    $('#district').html('<option value="">Select District</option>').prop('disabled', true);
                    $('#officename').html('<option value="">Select Office Name</option>').prop('disabled', true);
                    $('#pincodeTable').hide();
                    $('#noResults').text('');
                }
            });

            $('#district').change(function() {
                var state = $.trim($('#state').val());
                var district = $.trim($(this).val());
                console.log('District changed. State: ' + state + ', District: ' + district);
                if (district) {
                    console.log('Fetching offices for State: ' + state + ', District: ' + district);
                    $.ajax({
                        url: 'search.php',
                        type: 'POST',
                        data: { state: state, district: district, action: 'get_offices' },
                        success: function(data) {
                            console.log('Office Names received: ' + data);
                            $('#officename').html(data).prop('disabled', false);
                            if (data.trim() === '<option value="">Select Office Name</option>') {
                                console.log('No office names returned.');
                                alert('No office names found for this district.');
                            }
                            $('#pincodeTable').hide();
                            $('#noResults').text('');
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error: ' + error);
                            console.error('Status: ' + status);
                            console.error('Response: ' + xhr.responseText);
                            alert('Error fetching office names. Check the console for details.');
                        }
                    });
                } else {
                    console.log('No district selected, resetting office dropdown.');
                    $('#officename').html('<option value="">Select Office Name</option>').prop('disabled', true);
                    $('#pincodeTable').hide();
                    $('#noResults').text('');
                }
            });

            $('#officename').change(function() {
                var state = $.trim($('#state').val());
                var district = $.trim($('#district').val());
                var officename = $.trim($(this).val());
                console.log('Fetching details for State: ' + state + ', District: ' + district + ', Office: ' + officename);
                if (officename) {
                    $.ajax({
                        url: 'search.php',
                        type: 'POST',
                        data: { state: state, district: district, officename: officename, action: 'get_details' },
                        success: function(data) {
                            console.log('Details received: ' + data);
                            $('#tableBody').html(data);
                            if (data.trim()) {
                                $('#pincodeTable').show();
                                $('#noResults').text('');
                            } else {
                                $('#pincodeTable').hide();
                                $('#noResults').text('No results found.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error: ' + error);
                            console.error('Status: ' + status);
                            console.error('Response: ' + xhr.responseText);
                            alert('Error fetching details.');
                        }
                    });
                } else {
                    $('#pincodeTable').hide();
                    $('#noResults').text('');
                }
            });
        });
    </script>
</body>
</html>