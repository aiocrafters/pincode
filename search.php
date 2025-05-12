<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'india_pincode');

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }

        $action = $_POST['action'];
        $response = [];

        switch ($action) {
            case 'get_states':
                $sql = "SELECT DISTINCT statename FROM pincodes ORDER BY statename";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch states");
                $response['states'] = $result->fetch_all(MYSQLI_ASSOC);
                break;

            case 'get_districts':
                if (!isset($_POST['state'])) throw new Exception("State parameter missing");
                $state = $conn->real_escape_string(trim($_POST['state']));
                $sql = "SELECT DISTINCT district FROM pincodes WHERE UPPER(statename) = UPPER('$state') ORDER BY district";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch districts");
                $response['districts'] = $result->fetch_all(MYSQLI_ASSOC);
                break;

            case 'get_offices':
                if (!isset($_POST['state']) || !isset($_POST['district'])) throw new Exception("Missing parameters");
                $state = $conn->real_escape_string(trim($_POST['state']));
                $district = $conn->real_escape_string(trim($_POST['district']));
                $sql = "SELECT DISTINCT officename FROM pincodes 
                        WHERE UPPER(statename) = UPPER('$state') 
                        AND UPPER(district) = UPPER('$district') 
                        ORDER BY officename";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch offices");
                $response['offices'] = $result->fetch_all(MYSQLI_ASSOC);
                break;

            case 'get_details':
                if (!isset($_POST['state']) || !isset($_POST['district']) || !isset($_POST['officename'])) {
                    throw new Exception("Missing parameters");
                }
                $state = $conn->real_escape_string(trim($_POST['state']));
                $district = $conn->real_escape_string(trim($_POST['district']));
                $officename = $conn->real_escape_string(trim($_POST['officename']));

                $sql = "SELECT * FROM pincodes 
                        WHERE UPPER(statename) = UPPER('$state') 
                        AND UPPER(district) = UPPER('$district') 
                        AND UPPER(officename) = UPPER('$officename') 
                        LIMIT 1";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch details");

                if ($result->num_rows > 0) {
                    $response['details'] = $result->fetch_assoc();
                } else {
                    $response['error'] = "No records found";
                }
                break;

            default:
                throw new Exception("Invalid action");
        }

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        if (isset($conn)) $conn->close();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Pincode Search with Map</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .search-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .result-card {
            border-left: 4px solid #0d6efd;
        }
        .loading {
            display: none;
        }
        #map {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            margin-top: 20px;
            z-index: 1;
        }
        .map-container {
            position: relative;
        }
        .map-placeholder {
            height: 300px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-top: 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h1 class="text-center mb-4"><i class="bi bi-geo-alt-fill"></i> India Pincode Search with Map</h1>

                <div class="search-box mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="state" class="form-label">State</label>
                            <select class="form-select" id="state">
                                <option value="">Select State</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="district" class="form-label">District</label>
                            <select class="form-select" id="district" disabled>
                                <option value="">Select District</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="officename" class="form-label">Office Name</label>
                            <select class="form-select" id="officename" disabled>
                                <option value="">Select Office</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="loading" class="text-center loading mb-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Searching...</p>
                </div>

                <div id="results" class="mb-4"></div>
                
                <div class="map-container">
                    <div id="map"></div>
                    <div id="map-placeholder" class="map-placeholder">
                        <div class="text-center">
                            <i class="bi bi-map" style="font-size: 2rem;"></i>
                            <p class="mt-2">Map will appear here when location data is available</p>
                        </div>
                    </div>
                </div>

                <div class="text-muted text-center small">
                    <p>Data sourced from India Post Pincode Directory | Map by OpenStreetMap</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map variable
        let map;
        let marker;
        
        $(document).ready(function() {
            loadStates();

            $('#state').change(function() {
                const state = $(this).val();
                if (state) {
                    loadDistricts(state);
                    $('#district').prop('disabled', false).html('<option value="">Loading...</option>');
                    $('#officename').prop('disabled', true).html('<option value="">Select Office</option>');
                    resetResults();
                } else {
                    $('#district, #officename').prop('disabled', true).html('<option value="">Select...</option>');
                    resetResults();
                }
            });

            $('#district').change(function() {
                const state = $('#state').val();
                const district = $(this).val();
                if (district) {
                    loadOffices(state, district);
                    $('#officename').prop('disabled', false).html('<option value="">Loading...</option>');
                    resetResults();
                } else {
                    $('#officename').prop('disabled', true).html('<option value="">Select Office</option>');
                    resetResults();
                }
            });

            $('#officename').change(function() {
                const state = $('#state').val();
                const district = $('#district').val();
                const officename = $(this).val();
                if (officename) {
                    loadDetails(state, district, officename);
                } else {
                    resetResults();
                }
            });

            function loadStates() {
                showLoading(true);
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_states' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        const $stateSelect = $('#state').html('<option value="">Select State</option>');
                        response.states.forEach(state => {
                            $stateSelect.append(`<option value="${state.statename}">${state.statename}</option>`);
                        });
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to load states: " + error);
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function loadDistricts(state) {
                showLoading(true);
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_districts', state: state },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        const $districtSelect = $('#district').html('<option value="">Select District</option>');
                        response.districts.forEach(district => {
                            $districtSelect.append(`<option value="${district.district}">${district.district}</option>`);
                        });
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to load districts: " + error);
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function loadOffices(state, district) {
                showLoading(true);
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_offices', state: state, district: district },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        const $officeSelect = $('#officename').html('<option value="">Select Office</option>');
                        if (response.offices.length === 0) {
                            $officeSelect.append('<option value="" disabled>No offices found</option>');
                        } else {
                            response.offices.forEach(office => {
                                $officeSelect.append(`<option value="${office.officename}">${office.officename}</option>`);
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to load offices: " + error);
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function loadDetails(state, district, officename) {
                showLoading(true);
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_details', state: state, district: district, officename: officename },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        if (!response.details) return showError("No details found for this office");
                        displayResults(response.details);
                        
                        // Initialize or update map if coordinates exist
                        if (response.details.latitude && response.details.longitude) {
                            initMap(response.details.latitude, response.details.longitude, response.details.officename);
                        } else {
                            hideMap();
                        }
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to load details: " + error);
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function displayResults(details) {
                $('#results').html(`
                    <div class="card result-card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">${details.officename || 'N/A'}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Pincode:</strong> ${details.pincode || 'N/A'}</p>
                                    <p><strong>State:</strong> ${details.statename || 'N/A'}</p>
                                    <p><strong>District:</strong> ${details.district || 'N/A'}</p>
                                    <p><strong>Circle:</strong> ${details.circlename || 'N/A'}</p>
                                    <p><strong>Region:</strong> ${details.regionname || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Division:</strong> ${details.divisionname || 'N/A'}</p>
                                    <p><strong>Office Type:</strong> ${details.officetype || 'N/A'}</p>
                                    <p><strong>Delivery:</strong> ${details.delivery || 'N/A'}</p>
                                    <p><strong>Latitude:</strong> ${details.latitude || 'N/A'}</p>
                                    <p><strong>Longitude:</strong> ${details.longitude || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }

            function initMap(lat, lng, title) {
                // Hide placeholder and show map
                $('#map-placeholder').hide();
                $('#map').show();
                
                if (!map) {
                    // Initialize map if not already done
                    map = L.map('map').setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);
                } else {
                    // Update existing map view
                    map.setView([lat, lng], 15);
                }
                
                // Remove existing marker if any
                if (marker) {
                    map.removeLayer(marker);
                }
                
                // Add new marker
                marker = L.marker([lat, lng]).addTo(map)
                    .bindPopup(title)
                    .openPopup();
            }
            
            function hideMap() {
                $('#map').hide();
                $('#map-placeholder').show();
                if (map) {
                    map.off();
                    map.remove();
                    map = null;
                }
            }

            function resetResults() {
                $('#results').html('');
                hideMap();
            }

            function showLoading(show) {
                $('#loading').toggle(show);
            }

            function showError(message) {
                $('#results').html(`
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> ${message}
                    </div>
                `);
                hideMap();
            }
        });
    </script>
</body>
</html>