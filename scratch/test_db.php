<?php
try {
    $db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=retirement_society', 'root', '505974');
    $members = $db->query("SELECT * FROM members")->fetchAll(PDO::FETCH_ASSOC);
    print_r($members);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
