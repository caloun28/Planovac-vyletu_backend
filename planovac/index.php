<?php
header('Content-Type: application/json; charset=utf-8');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $uri === '/') {
    echo json_encode([
        "service" => "Trip Planner API",
        "version" => "1.0",
        "description" => "Core API for trip planning, integrating weather and local attractions.",
        "endpoints" => [
            "GET /" => "Dokumentace Plánovače",
            "GET /attractions" => "Dokumentace a vysvětlení k lokálním atrakcím (Python API)",
            "GET /locality/date-range?city={city}" => "Returns offered services, weather, and attractions for the specified city.",
            "POST /plan-trip" => "Creates a trip order (number of people, dates, attractions, etc.)."
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($method === 'GET' && $uri === '/attractions') {
    $python_doc = @file_get_contents("http://127.0.0.1:8081/");
    if ($python_doc) {
        echo $python_doc;
    } else {
        echo json_encode(["error" => "Python API pro atrakce je momentálně nedostupné."], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($method === 'GET' && $uri === '/attractions/date/') {
    $city = $_GET['city'] ?? '';

    if (empty($city)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing 'city' parameter."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Dotaz na interní Python API
    $python_url = "http://127.0.0.1:8081/attractions/date/?city=" . urlencode($city);
    $python_data = @file_get_contents($python_url);

    if ($python_data) {
        echo $python_data;
    } else {
        echo json_encode(["error" => "Python API nedostupné."], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($method === 'GET' && $uri === '/locality/date-range') {
    $city = $_GET['city'] ?? 'Praha';


    $coords = [
        'Praha' => ['lat' => 50.088, 'lon' => 14.4208],
        'Brno' => ['lat' => 49.195, 'lon' => 16.6068],
        'Český Krumlov' => ['lat' => 48.8109, 'lon' => 14.3152],
        'Špindlerův Mlýn' => ['lat' => 50.7259, 'lon' => 15.6066]
    ];
    $lat = $coords[$city]['lat'] ?? 50.088;
    $lon = $coords[$city]['lon'] ?? 14.4208;

    $weather_url = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&current_weather=true";
    $weather_data = @file_get_contents($weather_url);
    $weather = $weather_data ? json_decode($weather_data, true)['current_weather'] : null;

    $attractions_url = "http://127.0.0.1:8081/attractions/date/?city=" . urlencode($city);
    $attractions_data = @file_get_contents($attractions_url);

    if ($attractions_data) {
        $attractions = json_decode($attractions_data, true)['attractions'] ?? [];
    } else {
        $attractions = ["error" => "Local Attractions API is currently unavailable."];
    }

    $hotel_payload = json_encode([
        "location" => $city,
        "check_in" => "2026-10-23",
        "check_out" => "2026-10-27",
        "max_price" => 3000,
        "persons" => 1
    ]);

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $hotel_payload,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $hotels_response = @file_get_contents("http://192.168.2.170:5000/search-hotels", false, $context);
    $hotels = $hotels_response ? json_decode($hotels_response, true) : ["error" => "Hotely nedostupné"];

    echo json_encode([
        "locality" => $city,
        "services_offered" => [
            "weather_forecast" => "Open-Meteo API",
	    "hotels" => $hotels,
            "local_attractions" => "Internal Python API"
        ],
        "current_weather" => $weather,
        "attractions" => $attractions
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($method === 'POST' && $uri === '/plan-trip') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON payload."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $city = $input['location'] ?? '';
    $hotel_id = $input['hotel_id'] ?? '';
    $persons = $input['persons'] ?? 1;
    $selected_attractions = $input['attractions'] ?? [];

    $total_price_czk = 0;
    $cost_breakdown = [];

    $hotel_payload = json_encode([
        "location" => $city,
        "check_in" => $input['check_in'] ?? '',
        "check_out" => $input['check_out'] ?? '',
        "max_price" => 99999,
        "persons" => $persons
    ]);
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $hotel_payload,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $hotels_response = @file_get_contents("http://192.168.2.170:5000/search-hotels", false, $context);
    $hotels = $hotels_response ? json_decode($hotels_response, true) : [];

    if (is_array($hotels)) {
        foreach ($hotels as $h) {
            if (($h['hotel_code'] ?? '') === $hotel_id) {
                $cost_breakdown['hotel'] = ["name" => $h['hotel_name'], "price_czk" => $h['total_price_czk']];
                $total_price_czk += $h['total_price_czk'];
                break;
            }
        }
    }

    $cost_breakdown['attractions'] = [];
    if (!empty($city) && !empty($selected_attractions)) {
        $python_url = "http://127.0.0.1:8081/attractions/date/?city=" . urlencode($city);
        $python_data = @file_get_contents($python_url);
        $available_attractions = $python_data ? json_decode($python_data, true)['attractions'] ?? [] : [];

        foreach ($available_attractions as $attr) {
            if (in_array($attr['id'], $selected_attractions)) {
                $price_for_all = ($attr['pricing']['price_czk'] ?? 0) * $persons;
                $total_price_czk += $price_for_all;

                $cost_breakdown['attractions'][] = [
                    "name" => $attr['name'],
                    "price_total_czk" => $price_for_all
                ];
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "Trip order created successfully.",
        "calculated_total_czk" => $total_price_czk,
        "cost_breakdown" => $cost_breakdown,
        "order_details" => $input
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Endpoint not found. Use GET / for API documentation."], JSON_UNESCAPED_UNICODE);
