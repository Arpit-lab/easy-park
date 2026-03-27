<?php
/**
 * Parking Availability Prediction API
 * 
 * Predicts parking availability based on:
 * - Historical entry/exit patterns by hour
 * - Current time
 * - Occupancy trends
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get prediction
    $prediction = getParkingPrediction();
    
    if (!$prediction) {
        throw new Exception('Unable to generate prediction - insufficient data');
    }
    
    // Build response with explanation
    $response = [
        'success' => true,
        'current_status' => $prediction['status'],
        'severity' => $prediction['severity'],
        'details' => [
            'occupancy_percent' => $prediction['occupancy_percent'] . '%',
            'current_vehicles' => $prediction['current_vehicles'],
            'total_spaces' => $prediction['total_spaces'],
            'available_spaces' => $prediction['available_spaces'],
            'current_hour' => intval($prediction['current_hour']),
        ],
        'recommendation' => generatePredictionExplanation($prediction),
        'best_time_to_park' => $prediction['best_time'],
        'hourly_breakdown' => $prediction['hourly_data']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Generate recommendation message
 */
function generatePredictionExplanation($prediction) {
    $occupancy = $prediction['occupancy_percent'];
    $current_hour = intval($prediction['current_hour']);
    $best_hour = intval($prediction['best_hour']);
    
    $messages = [];
    
    // Current status message
    if ($occupancy >= 80) {
        $messages[] = "⚠️ Parking is currently congested ({$occupancy}% full)";
        $messages[] = "Consider waiting or using alternative parking";
    } elseif ($occupancy >= 50) {
        $messages[] = "ℹ️ Moderate parking availability ({$occupancy}% full)";
        $messages[] = "Finding a spot may take a few minutes";
    } else {
        $messages[] = "✅ Good parking availability ({$occupancy}% full)";
        $messages[] = "Easy to find available spots";
    }
    
    // Best time recommendation
    if ($current_hour != $best_hour) {
        $messages[] = "💡 " . $prediction['best_time'] . " is typically less crowded";
    }
    
    return implode(" | ", $messages);
}

?>
