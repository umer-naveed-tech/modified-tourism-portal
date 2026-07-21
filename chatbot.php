<?php
/**
 * chatbot.php — AI assistant backend.
 * Called via fetch() from chatbot_widget.php. Builds a system prompt from
 * REAL, current data in the database (hotels, cars, visa services) so the
 * bot never invents prices or hotel names, then calls Groq's free API.
 */
require_once 'config.php'; // gives us $pdo, csrf helpers, secrets

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// This endpoint receives a JSON body, so the token comes from there —
// not from $_POST (which csrf_valid() checks).
$raw = json_decode(file_get_contents('php://input'), true);
$submittedToken = $raw['csrf_token'] ?? '';
if(empty($_SESSION['csrf_token']) || !is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    echo json_encode(['reply' => 'Session expired — please refresh the page and try again.']);
    exit();
}

// ---------------- Basic rate limiting (protects the free Groq quota) ----------------
$limit = 25;              // max messages
$windowSeconds = 3600;    // per hour, per visitor session

if(empty($_SESSION['chat_window_start']) || (time() - $_SESSION['chat_window_start']) > $windowSeconds) {
    $_SESSION['chat_window_start'] = time();
    $_SESSION['chat_count'] = 0;
}
if($_SESSION['chat_count'] >= $limit) {
    echo json_encode(['reply' => 'Aap ne bohot zyada messages bhej diye hain — thodi dair baad try karein.']);
    exit();
}

// ---------------- Read & validate input ----------------
$userMessage = trim($raw['message'] ?? '');
$history = is_array($raw['history'] ?? null) ? $raw['history'] : [];

if($userMessage === '' || mb_strlen($userMessage) > 600) {
    echo json_encode(['reply' => 'Please send a valid question (under 600 characters).']);
    exit();
}

// Keep only the last few turns to control token usage
$history = array_slice($history, -6);

// ---------------- Pull LIVE data from the database for grounding ----------------
$hotelSummary = [];
foreach(['Mecca', 'Madinah'] as $city) {
    $stmt = $pdo->prepare("SELECT MIN(price_per_night_sar) AS min_p, MAX(price_per_night_sar) AS max_p, COUNT(*) AS cnt FROM hotels_saudi WHERE city = ?");
    $stmt->execute([$city]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row && $row['cnt'] > 0) {
        $hotelSummary[] = "$city: {$row['cnt']} hotels, SAR {$row['min_p']}–{$row['max_p']} per night";
    }
}

$carSummary = [];
$stmt = $pdo->query("SELECT car_name, car_model, capacity FROM cars");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $car) {
    $carSummary[] = trim($car['car_name'] . ' ' . $car['car_model']) . " ({$car['capacity']} persons)";
}

$visaSummary = [];
$stmt = $pdo->query("SELECT title, price FROM services WHERE service_type = 'visa'");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $visaSummary[] = $v['title'] . ' (Rs. ' . number_format($v['price']) . ')';
}

$systemPrompt = "You are the AI assistant for Ahmed Travels, a Saudi Arabia travel booking website (hotels in Mecca/Madinah, airport taxi, visa services). "
    . "Answer only using the CURRENT data given below — never invent hotel names, car types, or prices that aren't listed here. "
    . "Keep answers short (2-4 sentences), friendly, and practical. If asked to actually book something, tell the user to use the "
    . "'Services' page on the site to complete their booking — you cannot make bookings yourself. "
    . "Reply in the same language/style the user writes in (English, Urdu, or Roman Urdu). "
    . "Hotel cancellation policy: bookings can be cancelled free of charge within 60 minutes of booking, after that they cannot be cancelled.\n\n"
    . "CURRENT HOTELS:\n" . (empty($hotelSummary) ? "No hotel data available right now." : implode("\n", $hotelSummary)) . "\n\n"
    . "CURRENT TAXI/CAR OPTIONS:\n" . (empty($carSummary) ? "No car data available right now." : implode("\n", $carSummary)) . "\n\n"
    . "CURRENT VISA SERVICES:\n" . (empty($visaSummary) ? "No visa services listed right now." : implode("\n", $visaSummary));

// ---------------- Build message list for Groq ----------------
$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach($history as $turn) {
    if(isset($turn['role'], $turn['content']) && in_array($turn['role'], ['user', 'assistant'])) {
        $messages[] = ['role' => $turn['role'], 'content' => mb_substr((string)$turn['content'], 0, 600)];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

// ---------------- Call Groq API ----------------
$payload = json_encode([
    'model' => 'llama-3.3-70b-versatile',
    'messages' => $messages,
    'temperature' => 0.4,
    'max_tokens' => 350,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if($curlError || $httpCode != 200) {
    // Never leak raw API errors or the key to the client
    error_log('Groq API error: ' . $curlError . ' | HTTP ' . $httpCode . ' | ' . $response);
    echo json_encode(['reply' => 'Sorry, the assistant is temporarily unavailable. Please try again in a moment, or contact us directly.']);
    exit();
}

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? null;

if(!$reply) {
    echo json_encode(['reply' => 'Sorry, I could not generate a response. Please try rephrasing your question.']);
    exit();
}

$_SESSION['chat_count']++;

echo json_encode(['reply' => $reply]);