<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Process get request to fetch audio files for a specific language
if (!isset($_GET['lang']) || empty($_GET['lang'])) {
    echo "Language parameter is missing or empty.";
    exit;
}

// Process get request to fetch audio files for a specific language
if (!isset($_GET['pass']) || empty($_GET['pass']) && $_GET['pass'] !== '0000') {
    echo "Password parameter is missing or empty.";
    exit;
}

$language = $_GET['lang'];

// Get the latest words from the database for the specified language
$db = new PDO('sqlite:/var/www/html/kuku/kuku/data/kuku_db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->prepare("SELECT LOWER(GROUP_CONCAT(q.foreign_phrase, ' '))
FROM questions q
JOIN lessons l ON q.lesson_id = l.id
JOIN courses c ON c.id = l.course_id
WHERE c.language = :language;");

$stmt->execute([':language' => $language]);
$latest_words = $stmt->fetchAll(PDO::FETCH_COLUMN);
$latest_words = implode(' ', $latest_words); // Convert array to string
$latest_words = str_replace(['...', '.', ',', '!', '?', '(', ')',  '"', "'"], '', $latest_words);
$latest_words = mb_strtolower($latest_words, 'UTF-8'); // Convert to lowercase with UTF-8 encoding
$latest_words = explode(' ', $latest_words);
$latest_words = array_unique($latest_words); // Remove duplicate words
$latest_words = array_filter($latest_words, function($word) {
    return !empty(trim($word)); // Remove empty words, JIC
});

// Load existing words from the file

//$existing_words = file_get_contents($language . '/' . $language . '_existing.txt');
$existing_words_file_path = $this->audioPath . $language . '/' . $language . '_existing.txt';
$existing_words = file_exists($existing_words_file_path) ? file_get_contents($existing_words_file_path) : '';
$existing_words = explode(' ', $existing_words) ?? [];

// Remove duplicate words from existing words

$new_words = array_diff($latest_words, $existing_words);

// Save new words as audio files

foreach ($new_words as $word) {
    sleep(1);
    $word = urlencode($word);
    $url = "https://translate.google.com/translate_tts?ie=UTF-8&tl=$language&client=tw-ob&q=$word";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0'); // Required to avoid 403
    $audioData = curl_exec($ch);
    curl_close($ch);

    $filename = $language . "/mp3/" . urldecode($word) . "_$language" . ".mp3";
    file_put_contents($filename, $audioData);
    echo "Saved $filename\n";
}

// Append new words to existing words file

$existing_words = array_merge($existing_words, $new_words);
file_put_contents($language .'/'. $language . '_existing.txt', implode(' ', $existing_words));
