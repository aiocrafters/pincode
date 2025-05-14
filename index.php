<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'india_pincode');

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper function to format name for URL
function formatForUrl($name) {
    // Convert to lowercase
    $name = strtolower($name);
    // Handle postal abbreviations before replacing spaces
    $name = str_replace([' b.o.', ' s.o.', ' b.o', ' s.o'], ['_bo', '_so', '_bo', '_so'], $name);
    // Replace special characters with underscores
    $name = preg_replace('/[\s+.\\/&;:%]+/', '_', $name);
    return trim($name, '_');
}

// Helper function to parse URL-formatted name
function parseUrlName($urlName) {
    // Replace underscores with spaces
    $name = str_replace('_', ' ', $urlName);
    // Handle postal abbreviations
    $name = str_replace([' bo', ' so'], [' B.O', ' S.O'], $name);
    // Capitalize words
    $name = ucwords(strtolower($name));
    return $name;
}

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
                $states = $result->fetch_all(MYSQLI_ASSOC);
                $response['states'] = array_map(function($state) {
                    return [
                        'statename' => $state['statename'],
                        'url_statename' => formatForUrl($state['statename'])
                    ];
                }, $states);
                break;

            case 'get_districts':
                if (!isset($_POST['state'])) throw new Exception("State parameter missing");
                $state = $conn->real_escape_string(trim($_POST['state']));
                $sql = "SELECT DISTINCT district FROM pincodes WHERE UPPER(statename) = UPPER('$state') ORDER BY district";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch districts");
                $districts = $result->fetch_all(MYSQLI_ASSOC);
                $response['districts'] = array_map(function($district) {
                    return [
                        'district' => $district['district'],
                        'url_district' => formatForUrl($district['district'])
                    ];
                }, $districts);
                break;

            case 'get_offices':
                if (!isset($_POST['state']) || !isset($_POST['district'])) throw new Exception("Missing parameters");
                $state = $conn->real_escape_string(trim($_POST['state']));
                $district = $conn->real_escape_string(trim($_POST['district']));
                $sql = "SELECT DISTINCT officename, pincode FROM pincodes 
                        WHERE UPPER(statename) = UPPER('$state') 
                        AND UPPER(district) = UPPER('$district') 
                        ORDER BY officename";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch offices");
                $offices = $result->fetch_all(MYSQLI_ASSOC);
                $response['offices'] = array_map(function($office) {
                    return [
                        'officename' => $office['officename'],
                        'url_officename' => formatForUrl($office['officename']),
                        'pincode' => $office['pincode']
                    ];
                }, $offices);
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
                    $details = $result->fetch_assoc();
                    $details['url_statename'] = formatForUrl($details['statename']);
                    $details['url_district'] = formatForUrl($details['district']);
                    $details['url_officename'] = formatForUrl($details['officename']);
                    $response['details'] = $details;
                } else {
                    $response['error'] = "No records found";
                }
                break;

            case 'parse_url':
                if (!isset($_POST['url'])) throw new Exception("URL parameter missing");
                $url = trim($_POST['url']);
                $parts = explode('-', $url);
                if (count($parts) !== 4) throw new Exception("Invalid URL format");

                $state = $conn->real_escape_string(parseUrlName($parts[0]));
                $district = $conn->real_escape_string(parseUrlName($parts[1]));
                $officename = $conn->real_escape_string(parseUrlName($parts[2]));
                $pincode = $conn->real_escape_string($parts[3]);

                // More flexible query to handle abbreviations
                $sql = "SELECT * FROM pincodes 
                        WHERE UPPER(statename) = UPPER('$state') 
                        AND UPPER(district) = UPPER('$district')
                        AND (
                            UPPER(officename) = UPPER('$officename')
                            OR UPPER(REPLACE(officename, '.', '')) = UPPER(REPLACE('$officename', '.', ''))
                            OR UPPER(REPLACE(officename, ' ', '')) = UPPER(REPLACE('$officename', ' ', ''))
                        )
                        AND pincode = '$pincode' 
                        LIMIT 1";
                
                error_log("Parsing URL: $url");
                error_log("Executing query: $sql");

                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to parse URL");

                if ($result->num_rows > 0) {
                    $details = $result->fetch_assoc();
                    $details['url_statename'] = formatForUrl($details['statename']);
                    $details['url_district'] = formatForUrl($details['district']);
                    $details['url_officename'] = formatForUrl($details['officename']);
                    $response['details'] = $details;
                } else {
                    // Try one more time with more flexible matching
                    $sql = "SELECT * FROM pincodes 
                            WHERE UPPER(statename) = UPPER('$state') 
                            AND UPPER(district) = UPPER('$district')
                            AND (
                                UPPER(officename) LIKE UPPER('%".str_replace(' ', '%', $officename)."%')
                                OR UPPER(REPLACE(officename, '.', '')) LIKE UPPER('%".str_replace(' ', '%', $officename)."%')
                            )
                            AND pincode = '$pincode' 
                            LIMIT 1";
                    
                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        $details = $result->fetch_assoc();
                        $details['url_statename'] = formatForUrl($details['statename']);
                        $details['url_district'] = formatForUrl($details['district']);
                        $details['url_officename'] = formatForUrl($details['officename']);
                        $response['details'] = $details;
                    } else {
                        $response['error'] = "No records found for this URL";
                    }
                }
                break;

            case 'get_nearby_pincodes':
                if (!isset($_POST['state']) || !isset($_POST['district'])) throw new Exception("Missing parameters");
                $state = $conn->real_escape_string(trim($_POST['state']));
                $district = $conn->real_escape_string(trim($_POST['district']));
                $sql = "SELECT officename, pincode, statename, district 
                        FROM pincodes 
                        WHERE UPPER(statename) = UPPER('$state') 
                        AND UPPER(district) = UPPER('$district')
                        ORDER BY officename";
                $result = $conn->query($sql);
                if (!$result) throw new Exception("Failed to fetch nearby pincodes");
                $pincodes = $result->fetch_all(MYSQLI_ASSOC);
                $response['nearby_pincodes'] = array_map(function($office) {
                    return [
                        'officename' => $office['officename'],
                        'url_officename' => formatForUrl($office['officename']),
                        'pincode' => $office['pincode'],
                        'statename' => $office['statename'],
                        'url_statename' => formatForUrl($office['statename']),
                        'district' => $office['district'],
                        'url_district' => formatForUrl($office['district'])
                    ];
                }, $pincodes);
                break;

            default:
                throw new Exception("Invalid action");
        }

        echo json_encode($response);

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        if (isset($conn)) $conn->close();
        exit;
    }
}
?>

<!-- Rest of your HTML remains the same -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Search Indian pincodes with detailed information including state, district, office details, and geographical coordinates">
    <title>India Pincode Search with Map | Find Postal Codes</title>
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
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0c63e4;
        }
        .seo-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        #nearby-pincodes {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
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

                <div id="nearby-pincodes" class="mt-4" style="display: none;">
                    <h4 class="mb-3">Nearby Pincodes in this District</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Post Office Name</th>
                                    <th>Pincode</th>
                                    <th>State</th>
                                    <th>District</th>
                                </tr>
                            </thead>
                            <tbody id="nearby-pincodes-body">
                                <!-- Table content will be loaded here -->
                            </tbody>
                        </table>
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

        // Helper function to format name for URL
        function formatForUrl(name) {
            return name.toLowerCase().replace(/[\s+.\\/&;:%]+/g, '_').replace(/_+$/, '');
        }

        $(document).ready(function() {
            // Check for URL parameters in new format
            const query = window.location.search.substring(1); // Remove '?'
            if (query) {
                // Parse URL (e.g., jammu_and_kashmir-srinagar-gpo-190001)
                parseUrl(query);
            } else {
                loadStates();
            }

            $('#state').change(function() {
                const state = $(this).val();
                if (state) {
                    loadDistricts(state);
                    $('#district').prop('disabled', false).html('<option value="">Loading...</option>');
                    $('#officename').prop('disabled', true).html('<option value="">Select Office</option>');
                    resetResults();
                    updateUrl({ state });
                } else {
                    $('#district, #officename').prop('disabled', true).html('<option value="">Select...</option>');
                    resetResults();
                    updateUrl({});
                }
            });

            $('#district').change(function() {
                const state = $('#state').val();
                const district = $(this).val();
                if (district) {
                    loadOffices(state, district);
                    $('#officename').prop('disabled', false).html('<option value="">Loading...</option>');
                    resetResults();
                    updateUrl({ state, district });
                } else {
                    $('#officename').prop('disabled', true).html('<option value="">Select Office</option>');
                    resetResults();
                    updateUrl({ state });
                }
            });

            $('#officename').change(function() {
                const state = $('#state').val();
                const district = $('#district').val();
                const officename = $(this).val();
                if (pincodeMap[officename]) {
                    loadDetails(state, district, officename);
                    updateUrl({ state, district, officename, pincode: pincodeMap[officename] });
                } else {
                    resetResults();
                    updateUrl({ state, district });
                }
            });

            // Store pincode for each office to use in URL
            let pincodeMap = {};

            function parseUrl(url) {
                showLoading(true);
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'parse_url', url: url },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        if (!response.details) return showError("No details found for this URL");
                        
                        // Load states, then set state and load districts
                        loadStates().then(() => {
                            $('#state').val(response.details.statename).trigger('change');
                            loadDistricts(response.details.statename).then(() => {
                                $('#district').val(response.details.district).trigger('change');
                                loadOffices(response.details.statename, response.details.district).then(() => {
                                    $('#officename').val(response.details.officename).trigger('change');
                                });
                            });
                        });
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to parse URL: " + error);
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function loadStates() {
                showLoading(true);
                return $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_states' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        const $stateSelect = $('#state').html('<option value="">Select State</option>');
                        response.states.forEach(state => {
                            $stateSelect.append(`<option value="${state.statename}" data-url="${state.url_statename}">${state.statename}</option>`);
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
                return $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_districts', state: state },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        const $districtSelect = $('#district').html('<option value="">Select District</option>');
                        response.districts.forEach(district => {
                            $districtSelect.append(`<option value="${district.district}" data-url="${district.url_district}">${district.district}</option>`);
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
                return $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_offices', state: state, district: district },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) return showError(response.error);
                        const $officeSelect = $('#officename').html('<option value="">Select Office</option>');
                        pincodeMap = {};
                        if (response.offices.length === 0) {
                            $officeSelect.append('<option value="" disabled>No offices found</option>');
                        } else {
                            response.offices.forEach(office => {
                                $officeSelect.append(`<option value="${office.officename}" data-url="${office.url_officename}" data-pincode="${office.pincode}">${office.officename}</option>`);
                                pincodeMap[office.officename] = office.pincode;
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
                        
                        if (response.details.latitude && response.details.longitude) {
                            initMap(response.details.latitude, response.details.longitude, response.details.officename);
                        } else {
                            hideMap();
                        }
                        
                        loadNearbyPincodes(state, district);
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to load details: " + error);
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function loadNearbyPincodes(state, district) {
                showLoading(true);
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'get_nearby_pincodes', state: state, district: district },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            $('#nearby-pincodes').hide();
                            return;
                        }
                        
                        const $tbody = $('#nearby-pincodes-body').empty();
                        
                        if (response.nearby_pincodes && response.nearby_pincodes.length > 0) {
                            response.nearby_pincodes.forEach(office => {
                                const url = `?${office.url_statename}-${office.url_district}-${office.url_officename}-${office.pincode}`;
                                $tbody.append(`
                                    <tr>
                                        <td><a href="${url}">${office.officename}</a></td>
                                        <td>${office.pincode || 'N/A'}</td>
                                        <td>${office.statename || 'N/A'}</td>
                                        <td>${office.district || 'N/A'}</td>
                                    </tr>
                                `);
                            });
                            $('#nearby-pincodes').show();
                        } else {
                            $('#nearby-pincodes').hide();
                        }
                    },
                    error: function() {
                        $('#nearby-pincodes').hide();
                    },
                    complete: function() {
                        showLoading(false);
                    }
                });
            }

            function displayResults(details) {
                const formattedLat = details.latitude ? parseFloat(details.latitude).toFixed(6) : 'N/A';
                const formattedLng = details.longitude ? parseFloat(details.longitude).toFixed(6) : 'N/A';
                
                document.title = `Pincode ${details.pincode || ''} - ${details.district || ''}, ${details.statename || ''} | Postal Details`;
                $('meta[name="description"]').attr('content', 
                    `Find detailed postal information for Pincode ${details.pincode || ''} in ${details.district || ''}, ${details.statename || ''}. Learn about postal services, geographical details, delivery options, and more.`);
                
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
                                    <p><strong>Latitude:</strong> ${formattedLat}</p>
                                    <p><strong>Longitude:</strong> ${formattedLng}</p>
                                </div>
                            </div>

                            <section class="seo-section">
                                <h1>Pincode <strong>${details.pincode || 'N/A'}</strong> - ${details.district || 'N/A'}, ${details.statename || 'N/A'} | Postal Details</h1>
                                <br><br>
                                <h2>The Pincode of ${details.officename || 'N/A'} is <strong>${details.pincode || 'N/A'}</strong></h2>
                                <p>The pincode <strong>${details.pincode || 'N/A'}</strong> serves areas in <strong>${details.district || 'N/A'}</strong>, located in <strong>${details.statename || 'N/A'}</strong>. It is part of the <strong>${details.circlename || 'N/A'}</strong> postal circle, within the <strong>${details.regionname || 'N/A'}</strong> region, and managed by the <strong>${details.divisionname || 'N/A'}</strong> division. The pincode supports <strong>${details.delivery || 'N/A'}</strong> delivery services for fast mail and parcel delivery.</p>
                            </section>

                            <section class="seo-section">
                                <h2>Frequently Asked Questions (FAQs)</h2>
                                <div class="accordion" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h3 class="accordion-header" id="headingOne">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                                What is the pincode for ${details.district || 'N/A'}, ${details.statename || 'N/A'}?
                                            </button>
                                        </h3>
                                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                The pincode for areas in <strong>${details.district || 'N/A'}</strong>, <strong>${details.statename || 'N/A'}</strong> is <strong>${details.pincode || 'N/A'}</strong>. It falls under the <strong>${details.circlename || 'N/A'}</strong> postal circle and is managed by the <strong>${details.divisionname || 'N/A'}</strong> division.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h3 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                                Does pincode ${details.pincode || 'N/A'} offer delivery services?
                                            </button>
                                        </h3>
                                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes, pincode <strong>${details.pincode || 'N/A'}</strong> supports <strong>${details.delivery || 'N/A'}</strong> delivery services through its <strong>${details.officetype || 'N/A'}</strong> office, ensuring prompt delivery of mail and parcels in <strong>${details.district || 'N/A'}</strong>.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h3 class="accordion-header" id="headingThree">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                                Where is pincode ${details.pincode || 'N/A'} located geographically?
                                            </button>
                                        </h3>
                                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Pincode <strong>${details.pincode || 'N/A'}</strong> is located at <strong>Latitude: ${formattedLat}</strong> and <strong>Longitude: ${formattedLng}</strong> in <strong>${details.district || 'N/A'}</strong>, <strong>${details.statename || 'N/A'}</strong>, providing a clear and accessible location for logistics and travel.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                `);
            }

            function initMap(lat, lng, title) {
                $('#map-placeholder').hide();
                $('#map').show();
                
                if (!map) {
                    map = L.map('map').setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);
                } else {
                    map.setView([lat, lng], 15);
                }
                
                if (marker) {
                    map.removeLayer(marker);
                }
                
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
                $('#nearby-pincodes').hide();
                hideMap();
                document.title = 'India Pincode Search with Map | Find Postal Codes';
                $('meta[name="description"]').attr('content', 'Search Indian pincodes with detailed information including state, district, office details, and geographical coordinates');
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
                $('#nearby-pincodes').hide();
            }

            function updateUrl(params) {
                const url = new URL(window.location);
                url.search = '';
                if (params.state && params.district && params.officename && params.pincode) {
                    const stateUrl = $('#state option:selected').data('url') || formatForUrl(params.state);
                    const districtUrl = $('#district option:selected').data('url') || formatForUrl(params.district);
                    const officenameUrl = $('#officename option:selected').data('url') || formatForUrl(params.officename);
                    url.search = `${stateUrl}-${districtUrl}-${officenameUrl}-${params.pincode}`;
                } else if (params.state && params.district) {
                    const stateUrl = $('#state option:selected').data('url') || formatForUrl(params.state);
                    const districtUrl = $('#district option:selected').data('url') || formatForUrl(params.district);
                    url.search = `${stateUrl}-${districtUrl}`;
                } else if (params.state) {
                    const stateUrl = $('#state option:selected').data('url') || formatForUrl(params.state);
                    url.search = `${stateUrl}`;
                }
                window.history.pushState({}, '', url);
            }
        });
    </script>
</body>
</html>