<?php
function test_login($email, $password) {
    $url = 'http://localhost/retirement/backend/api/auth/login';
    $ch = curl_init($url);
    $data = json_encode(['email' => $email, 'password' => $password]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($response, true)];
}

function test_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer $token"
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($response, true)];
}

echo "Testing login with admin123...\n";
$res = test_login('admin@society.org', 'admin123');
echo "Code: {$res['code']}\n";
print_r($res['body']);

if ($res['code'] !== 200) {
    echo "\nTesting login with 'password'...\n";
    $res = test_login('admin@society.org', 'password');
    echo "Code: {$res['code']}\n";
    print_r($res['body']);
}

if ($res['code'] === 200) {
    $token = $res['body']['data']['token'];
    echo "\nLogin Success! Token: " . substr($token, 0, 20) . "...\n";
    
    echo "\nTesting /api/auth/me...\n";
    $me = test_get('http://localhost/retirement/backend/api/auth/me', $token);
    echo "Code: {$me['code']}\n";
    print_r($me['body']);

    echo "\nTesting /api/reports/dashboard...\n";
    $dash = test_get('http://localhost/retirement/backend/api/reports/dashboard', $token);
    echo "Code: {$dash['code']}\n";
    print_r($dash['body']);
} else {
    echo "Login failed for both passwords.\n";
}
