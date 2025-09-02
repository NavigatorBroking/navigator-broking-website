<?php
/**
 * Mercury CRM API Integration Handler
 * Navigator Broking - Form Submission to Mercury CRM
 * 
 * Handles form submissions and creates Person/Opportunity records in Mercury
 */

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Mercury API Configuration
$mercury_config = [
    'base_url' => 'https://apis.connective.com.au/mercury/v1/',
    'api_token' => '3302ad97-cf0e-47af-867e-99a8371135b2',
    'api_key' => 'hbr8sOHfzb4aN6amyeNBS2rEl66pXtQY57JvMbuo'
];

/**
 * Make API request to Mercury CRM
 */
function callMercuryAPI($endpoint, $data, $config) {
    $url = $config['base_url'] . $config['api_token'] . '/' . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $config['api_key']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL Error: ' . $error);
    }
    
    return [
        'http_code' => $http_code,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}

/**
 * Create Person record in Mercury CRM
 */
function createPersonRecord($formData, $config) {
    // Parse name
    if (!empty($formData['fullName'])) {
        $nameParts = explode(' ', trim($formData['fullName']), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
    } else {
        $firstName = $formData['firstName'] ?? '';
        $lastName = $formData['lastName'] ?? '';
    }
    
    $personData = [
        'Names' => [
            [
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'IsPrimary' => true
            ]
        ],
        'ContactMethods' => [],
        'NotePad' => 'Lead from Navigator Broking website - ' . date('Y-m-d H:i:s')
    ];
    
    // Add contact methods
    if (!empty($formData['phone'])) {
        $personData['ContactMethods'][] = [
            'ContactType' => 'Phone',
            'ContactValue' => $formData['phone'],
            'IsPrimary' => true
        ];
    }
    
    if (!empty($formData['email'])) {
        $personData['ContactMethods'][] = [
            'ContactType' => 'Email', 
            'ContactValue' => $formData['email'],
            'IsPrimary' => empty($formData['phone']) // Primary if no phone
        ];
    }
    
    return callMercuryAPI('contacts', $personData, $config);
}

/**
 * Create Opportunity record in Mercury CRM
 */
function createOpportunityRecord($formData, $personId, $config) {
    $firstName = '';
    $lastName = '';
    
    if (!empty($formData['fullName'])) {
        $nameParts = explode(' ', trim($formData['fullName']), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
    } else {
        $firstName = $formData['firstName'] ?? '';
        $lastName = $nameParts[1] ?? '';
    }
    
    // Determine opportunity details based on source
    $opportunityType = 'Home Loan';
    $notes = "Website inquiry from Navigator Broking\n";
    
    switch ($formData['source'] ?? '') {
        case 'medical-professionals':
            $opportunityType = 'Medical Professional Loan';
            $notes .= "Medical Specialization: " . ($formData['specialization'] ?? 'Not specified') . "\n";
            break;
        case 'self-employed':
            $opportunityType = 'Self Employed Loan';
            $notes .= "Industry: " . ($formData['industry'] ?? 'Not specified') . "\n";
            $notes .= "Years Self-Employed: " . ($formData['yearsEmployed'] ?? 'Not specified') . "\n";
            break;
        case 'debt-consolidation':
            $opportunityType = 'Debt Consolidation';
            $notes .= "Debt Amount: " . ($formData['debtAmount'] ?? 'Not specified') . "\n";
            $notes .= "Debt Types: " . ($formData['debtTypes'] ?? 'Not specified') . "\n";
            break;
        case 'contact':
            $opportunityType = 'General Inquiry';
            $notes .= "Service Interest: " . ($formData['serviceInterest'] ?? 'Not specified') . "\n";
            break;
    }
    
    $notes .= "\nLoan Amount: " . ($formData['loanAmount'] ?? 'Not specified');
    $notes .= "\nSubmitted: " . date('Y-m-d H:i:s');
    
    $opportunityData = [
        'OpportunityName' => trim($firstName . ' ' . $lastName) . ' - ' . $opportunityType,
        'OpportunityType' => $opportunityType,
        'TransactionType' => 'Purchase',
        'LeadStatus' => 'New Lead',
        'NotePad' => $notes
    ];
    
    // Add loan amount if provided
    if (!empty($formData['loanAmount'])) {
        $opportunityData['LoanAmount'] = (float)str_replace(['$', ',', 'k', 'K'], '', $formData['loanAmount']);
    }
    
    return callMercuryAPI('opportunities', $opportunityData, $config);
}

// Main processing
try {
    $input = file_get_contents('php://input');
    $formData = json_decode($input, true);
    
    if (!$formData) {
        throw new Exception('Invalid form data received');
    }
    
    // Validate required fields
    $hasName = !empty($formData['fullName']) || (!empty($formData['firstName']) && !empty($formData['lastName']));
    if (!$hasName) {
        throw new Exception('Name is required');
    }
    
    if (empty($formData['phone']) && empty($formData['email'])) {
        throw new Exception('Phone number or email is required');
    }
    
    // Log submission
    error_log('Mercury Integration - Processing: ' . json_encode($formData));
    
    // Create Person record
    $personResponse = createPersonRecord($formData, $mercury_config);
    
    if ($personResponse['http_code'] < 200 || $personResponse['http_code'] >= 300) {
        error_log('Mercury Person Creation Failed: ' . $personResponse['raw_response']);
        throw new Exception('Failed to create contact record in CRM');
    }
    
    $personId = $personResponse['response']['Id'] ?? null;
    
    // Create Opportunity record
    $opportunityResponse = null;
    if ($personId) {
        try {
            $opportunityResponse = createOpportunityRecord($formData, $personId, $mercury_config);
            if ($opportunityResponse['http_code'] < 200 || $opportunityResponse['http_code'] >= 300) {
                error_log('Mercury Opportunity Creation Failed: ' . $opportunityResponse['raw_response']);
            }
        } catch (Exception $e) {
            error_log('Mercury Opportunity Error: ' . $e->getMessage());
        }
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your inquiry! We will contact you within 24 hours to discuss your mortgage needs.',
        'person_created' => true,
        'opportunity_created' => ($opportunityResponse && $opportunityResponse['http_code'] >= 200 && $opportunityResponse['http_code'] < 300)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    error_log('Mercury Integration Error: ' . $e->getMessage());
}
?>