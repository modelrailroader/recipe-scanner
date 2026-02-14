<?php

/*
 * To-Do:
 * @todo GitHub Repo erstellen
 * */

// Import credentials
require_once 'credentials.php';
header('Content-Type: application/json');

// Success status and message
$success = true;
$message = '✅ Erfolgreich importiert!';

//
// Get and validate image from form
//

// Only allow POST-method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// Check if file exists
if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Keine Datei empfangen']);
    exit;
}

$file = $_FILES['image'];

// Check on upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Upload-Fehler']);
    exit;
}

// Check mime-type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Dateiformat']);
    exit;
}

// Load picture for recipe-scanner
$imagePath = $_FILES['image']['tmp_name'];
$imageBase64 = base64_encode(file_get_contents($imagePath));

//
// Process image
//

// Request body for Google Vision API
$requestBody = [
    'requests' => [[
        'image' => [
            'content' => $imageBase64
        ],
        'features' => [[
            'type' => 'TEXT_DETECTION'
        ]]
    ]]
];

// cURL-Request for Vision API
$ch = curl_init('https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionAPIKey);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json; charset=utf-8'
    ],
    CURLOPT_POSTFIELDS     => json_encode($requestBody)
]);

$response = curl_exec($ch);

$ocrText = json_decode($response, true)
['responses'][0]['fullTextAnnotation']['text'] ?? '';



/**
 * Function for sending OCR-text to Gemini API including prompt and returning Schema.org-JSON
 *
 * @param string $ocrText
 * @param string apiKey
 * @return string JSON Schema.org-Recipe-Format
 */
function convertRecipeWithGemini(string $ocrText, string $apiKey)
{
    // Gemini API Model: Gemini 2.5-pro
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-pro:generateContent?key=" . $apiKey;

    // Gemini AI-prompt
    $systemInstruction = 'Rolle: Du bist ein spezialisierter Daten-Extraktor für kulinarische Rezepte. Deine Aufgabe ist es, unstrukturierten Text (häufig fehlerbehaftetes OCR) in ein perfekt formatiertes JSON-Objekt nach dem Standard Schema.org Recipe zu transformieren, welches direkt in das "Nextcloud Cookbook" importiert werden kann.

Strikte Ausgabe-Regel: Gib ausschließlich das valide JSON-Objekt aus. Erzeuge keinen Einleitungstext, keine Erklärungen und keine Markdown-Code-Blöcke (kein json ... ), es sei denn, der User verlangt explizit eine Formatierung für die Ansicht. Das Ziel ist die direkte maschinelle Verarbeitbarkeit.

Verarbeitungsschritte:

    OCR-Bereinigung: Entferne Seitenzahlen, Header, Werbesprüche und Bildbeschreibungen. Korrigiere offensichtliche Lesefehler (z.B. "1 Pck. Vanilling-Zucker" -> "1 Pck. Vanillin-Zucker").

    Strukturierung: Ordne Zutaten und Anweisungen logisch zu (Teig, Füllung, Deko).

    Zeit-Normalisierung: Wandle Zeitangaben strikt in das ISO 8601 Format um (z.B. PT50M). Erfinde keine Zeitangaben, die nicht im Rezept stehen hinzu. Lasse diese im Zweifel leer. Berechne die Gesamtzeit aus den Einzelzeiten, falls diese vorhanden sind.

    Mengen-Optimierung: Trenne Mengen und Zustände (z.B. "150 g Butter, weich"). Nutze für recipeYield nur eine ganze Zahl für die Skalierbarkeit in der App.

JSON-Struktur-Vorgaben:

    @context & @type: Immer "https://schema.org" und "Recipe".

    recipeIngredient: Nutze String-Trenner mit Doppelpunkt (z.B. "Für den Boden:"), um Kategorien im Cookbook zu erzeugen.

recipeInstructions: Nutze das HowToSection Format, um Schritte in logische Phasen zu unterteilen (Boden, Füllung, Abschluss).

Keywords: Erzeuge sinnvolle Tags basierend auf den Zutaten (z.B. "Kuchen, Vegetarisch, Dessert").

Nutrition: Falls Nährwertangaben im Text vorhanden sind, extrahiere diese präzise in das nutrition Objekt.

Eingabetext: ' . $ocrText;

    // Payload for Gemini API
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $systemInstruction]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
        ]
    ];

    // cURL-Request for Gemini API
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return "Fehler: " . curl_error($ch);
    }

    $result = json_decode($response, true);

    // Extract textContent from Gemini-Response
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    return "Fehler bei der Verarbeitung: " . $response;
}

$geminiResponse = convertRecipeWithGemini($ocrText, $googleGeminiAPIKey);

/*
 * Function for extracting delivered Gemini API-response to JSON-Object
 *
 * @param string Gemini-response
 * @return string JSON-response
 */
function extractJsonObject(string $text): string {
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');

    if ($start === false || $end === false) {
        throw new RuntimeException('Kein JSON gefunden');
    }

    return substr($text, $start, $end - $start + 1);
}

if (is_null(json_decode(extractJsonObject($geminiResponse)))) {
    echo json_encode(['success' => false, 'message' => 'Fehlerhaftes JSON von Gemini erhalten.']);
    exit;
}

$recipeJson = extractJsonObject($geminiResponse);

switch ($recipeCollection) {
    case 'Nextcloud':
        $ch = curl_init($nextcloudUrl . '/apps/cookbook/api/v1/recipes');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_USERPWD => $nextcloudUser . ':' . $nextcloudToken,
            CURLOPT_POSTFIELDS => $recipeJson,
            CURLOPT_RETURNTRANSFER => true
        ]);

        $result = curl_exec($ch);
    case 'Mealie':
        $ch = curl_init('https://rezepte.jans-allerlei.de/api/auth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => $mealieUsername,
                'password' => $mealiePassword,
                'remember_me' => false
            ]),
        ]);

        $response = curl_exec($ch);
        $token = json_decode($response, true)['access_token'] ?? null;

        if ($response === false) {
            echo json_encode(['success' => false, 'message' => 'Einloggen in Mealie nicht möglich.']);
            exit;
        }

        if (!$token) {
            echo json_encode(['success' => false, 'message' => 'Kein Bearer-Token erhalten.']);
            exit;
        }

        $payload = json_encode([
            'includeTags' => false,
            'data' => $recipeJson
        ]);

        $ch = curl_init('https://rezepte.jans-allerlei.de/api/recipes/create/html-or-json');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'includeTags' => false,
                'data' => $recipeJson
            ])
        ]);

        $recipeSlug = curl_exec($ch);

        if ($recipeSlug === false) {
            echo json_encode(['success' => false, 'message' => 'Mealie-Fehler: '  . curl_error($ch)]);
            exit;
        }
}

//
// Sending back response to form
//

echo json_encode([
    'success' => $success,
    'message' => $message
]);