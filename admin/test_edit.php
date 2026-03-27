<?php
/**
 * Test Edit Functionality
 * Access: http://localhost:8888/admin/test_edit.php
 */

require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$conn = getDB();

// Get first space for testing
$space = $conn->query("SELECT * FROM parking_spaces LIMIT 1")->fetch_assoc();

if (!$space) {
    echo "<h2>No parking spaces found. Please add some spaces first.</h2>";
    echo "<a href='manage_spaces.php'>Go to Manage Spaces</a>";
    exit;
}

$space_id = $space['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Edit Functionality - EasyPark</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1>🧪 Test Edit Functionality</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test AJAX Call</h5>
                    </div>
                    <div class="card-body">
                        <p>Testing space ID: <strong><?php echo $space_id; ?></strong></p>
                        <button id="testAjax" class="btn btn-primary">Test AJAX Call</button>
                        <div id="ajaxResult" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Direct Database Check</h5>
                    </div>
                    <div class="card-body">
                        <h6>Space Data from Database:</h6>
                        <pre><?php print_r($space); ?></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Manual Edit Test</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="manage_spaces.php">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="space_id" value="<?php echo $space['id']; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <label>Space Number</label>
                                    <input type="text" class="form-control" name="space_number" value="<?php echo $space['space_number']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label>Location Name</label>
                                    <input type="text" class="form-control" name="location_name" value="<?php echo $space['location_name']; ?>" required>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">Test Manual Edit</button>
                                <a href="manage_spaces.php" class="btn btn-secondary">Back to Manage Spaces</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#testAjax').click(function() {
                $('#ajaxResult').html('<div class="alert alert-info">Testing AJAX call...</div>');

                $.ajax({
                    url: 'get_space.php',
                    method: 'POST',
                    data: {space_id: <?php echo $space_id; ?>},
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#ajaxResult').html(`
                                <div class="alert alert-success">✅ AJAX call successful!</div>
                                <pre>${JSON.stringify(response, null, 2)}</pre>
                            `);
                        } else {
                            $('#ajaxResult').html(`
                                <div class="alert alert-danger">❌ AJAX call failed: ${response.message}</div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#ajaxResult').html(`
                            <div class="alert alert-danger">❌ AJAX error: ${status} - ${error}</div>
                            <pre>Response: ${xhr.responseText}</pre>
                        `);
                    }
                });
            });
        });
    </script>
</body>
</html>