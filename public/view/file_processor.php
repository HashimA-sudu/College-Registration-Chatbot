<?php
// ==============================
// 📁 File Processor for Chatbot
// Extracts text from images, PDFs, Word, Excel, PowerPoint
// ==============================

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use Dotenv\Dotenv;

// Load .env file
$dotenvPath = __DIR__ . '/../..';
error_log("file_processor.php: Attempting to load .env from: " . realpath($dotenvPath));
try {
    $dotenv = Dotenv::createImmutable($dotenvPath);
    $dotenv->load();
    error_log("file_processor.php: .env loaded successfully. OCR_API_KEY is " . (isset($_ENV['OCR_API_KEY']) ? 'SET' : 'NOT SET'));
} catch (Exception $e) {
    // .env file not found or already loaded, use fallback
    error_log('file_processor.php: Could not load .env file: ' . $e->getMessage());
    error_log("file_processor.php: OCR_API_KEY from \$_SERVER: " . ($_SERVER['OCR_API_KEY'] ?? 'NOT SET'));
}

/**
 * Extract text from uploaded file
 * @param array $file $_FILES array element
 * @return array ['success' => bool, 'text' => string, 'error' => string]
 */
function extractTextFromFile($file) {
    $result = ['success' => false, 'text' => '', 'error' => ''];

    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        $result['error'] = 'File upload failed';
        return $result;
    }

    $fileName = $file['name'];
    $filePath = $file['tmp_name'];
    $fileSize = $file['size'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Check file size (max 10MB)
    if ($fileSize > 10 * 1024 * 1024) {
        $result['error'] = 'File too large (max 10MB)';
        return $result;
    }

    error_log("Processing file: $fileName (type: $extension, size: $fileSize bytes)");

    try {
        switch ($extension) {
            case 'txt':
                $result = extractFromText($filePath);
                break;

            case 'pdf':
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'bmp':
                $result = extractFromImageOrPDF($filePath, $extension);
                break;

            case 'doc':
            case 'docx':
                $result = extractFromWord($filePath);
                break;

            case 'xls':
            case 'xlsx':
            case 'csv':
                $result = extractFromExcel($filePath, $extension);
                break;

            case 'ppt':
            case 'pptx':
                $result = extractFromPowerPoint($filePath);
                break;

            default:
                $result['error'] = "Unsupported file type: $extension";
        }

        if ($result['success']) {
            error_log("Successfully extracted " . strlen($result['text']) . " characters from $fileName");
        } else {
            error_log("Failed to extract text from $fileName: " . $result['error']);
        }

    } catch (Exception $e) {
        $result['error'] = 'Error processing file: ' . $e->getMessage();
        error_log("File processing error: " . $e->getMessage());
    }

    return $result;
}

/**
 * Extract text from plain text file
 */
function extractFromText($filePath) {
    $text = file_get_contents($filePath);
    return ['success' => true, 'text' => $text, 'error' => ''];
}

/**
 * Extract text from images or PDF using OCR.space API (FREE)
 */
function extractFromImageOrPDF($filePath, $extension) {
    // Check if curl is available
    if (!function_exists('curl_init')) {
        return ['success' => false, 'text' => '', 'error' => 'CURL extension not enabled in PHP'];
    }

    // Check file size (API limit is 1MB for free tier)
    $fileSize = filesize($filePath);
    if ($fileSize > 1024 * 1024) {
        return ['success' => false, 'text' => '', 'error' => 'File too large for OCR (max 1MB). Try a smaller image.'];
    }

    // Get API key from environment (.env file)
    $apiKey = $_ENV['OCR_API_KEY'] ?? $_SERVER['OCR_API_KEY'] ?? 'helloworld'; // 'helloworld' is the demo key
    error_log("Using OCR API key: " . substr($apiKey, 0, 5) . "... (length: " . strlen($apiKey) . ")");
    error_log("File size: " . $fileSize . " bytes (" . round($fileSize/1024, 2) . " KB)");

    // Use OCR.space API
    $apiUrl = 'https://api.ocr.space/parse/image';

    $fileData = file_get_contents($filePath);
    if ($fileData === false) {
        return ['success' => false, 'text' => '', 'error' => 'Failed to read file'];
    }

    $base64 = base64_encode($fileData);

    // Determine correct MIME type for base64 data URI
    $mimeType = ($extension === 'pdf') ? 'application/pdf' : 'image/' . $extension;

    $postData = [
        'base64Image' => 'data:' . $mimeType . ';base64,' . $base64,
        'language' => 'eng', // English
        'isOverlayRequired' => 'false',
        'detectOrientation' => 'true',
        'scale' => 'true',
        'OCREngine' => '2',
        'apikey' => $apiKey
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'text' => '', 'error' => 'CURL error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        error_log("OCR API HTTP error: $httpCode - Response: " . substr($response, 0, 500));
        return ['success' => false, 'text' => '', 'error' => "OCR API returned HTTP $httpCode"];
    }

    error_log("OCR API response received (length: " . strlen($response) . ")");
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("OCR API JSON parse error: " . json_last_error_msg());
        error_log("Raw response: " . substr($response, 0, 1000));
        return ['success' => false, 'text' => '', 'error' => 'Invalid JSON response from OCR API'];
    }

    if (isset($data['ParsedResults'][0]['ParsedText'])) {
        $text = $data['ParsedResults'][0]['ParsedText'];
        error_log("OCR success: Extracted " . strlen($text) . " characters");
        return ['success' => true, 'text' => trim($text), 'error' => ''];
    } else {
        $errorMsg = $data['ErrorMessage'][0] ?? $data['ErrorMessage'] ?? 'OCR failed';
        error_log("OCR API error: " . $errorMsg);
        error_log("Full response: " . json_encode($data));

        // Check if it's a language-related error
        if (strpos($errorMsg, 'language') !== false || strpos($errorMsg, 'E201') !== false) {
            return ['success' => false, 'text' => '', 'error' =>
                "⚠️ **Image Processing Notice**\n\n" .
                "The free OCR service supports **English text only** for images and PDFs.\n\n" .
                "💡 **Your options:**\n\n" .
                "• Upload **images/PDFs with English text**\n" .
                "• Upload **Word, Excel, or PowerPoint files** (supports Arabic & English)\n" .
                "• **Type your question directly** in Arabic or English\n\n" .
                "📝 Typing is the best option for Arabic content!"
            ];
        }

        return ['success' => false, 'text' => '', 'error' => "OCR error: $errorMsg"];
    }
}

/**
 * Extract text from Word document
 */
function extractFromWord($filePath) {
    try {
        $phpWord = WordIOFactory::load($filePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $text .= $childElement->getText() . "\n";
                        } elseif (method_exists($childElement, 'getContent')) {
                            $text .= $childElement->getContent() . "\n";
                        }
                    }
                }
            }
        }

        return ['success' => true, 'text' => trim($text), 'error' => ''];
    } catch (Exception $e) {
        return ['success' => false, 'text' => '', 'error' => 'Failed to read Word file: ' . $e->getMessage()];
    }
}

/**
 * Extract text from Excel/CSV
 */
function extractFromExcel($filePath, $extension) {
    try {
        if ($extension === 'csv') {
            $text = file_get_contents($filePath);
            return ['success' => true, 'text' => $text, 'error' => ''];
        }

        $spreadsheet = SpreadsheetIOFactory::load($filePath);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $text .= "Sheet: " . $sheet->getTitle() . "\n\n";

            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $text .= implode("\t", $rowData) . "\n";
            }
            $text .= "\n";
        }

        return ['success' => true, 'text' => trim($text), 'error' => ''];
    } catch (Exception $e) {
        return ['success' => false, 'text' => '', 'error' => 'Failed to read Excel file: ' . $e->getMessage()];
    }
}

/**
 * Extract text from PowerPoint
 */
function extractFromPowerPoint($filePath) {
    try {
        // For PPTX, we'll extract it as a ZIP and parse the XML
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $text = '';

            // Get slide count
            for ($i = 1; $i <= 100; $i++) {
                $slideContent = $zip->getFromName("ppt/slides/slide{$i}.xml");
                if ($slideContent === false) break;

                $xml = simplexml_load_string($slideContent);
                $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

                $textElements = $xml->xpath('//a:t');
                $text .= "Slide $i:\n";
                foreach ($textElements as $textElement) {
                    $text .= (string)$textElement . "\n";
                }
                $text .= "\n";
            }

            $zip->close();
            return ['success' => true, 'text' => trim($text), 'error' => ''];
        } else {
            return ['success' => false, 'text' => '', 'error' => 'Failed to open PowerPoint file'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'text' => '', 'error' => 'Failed to read PowerPoint file: ' . $e->getMessage()];
    }
}
?>
