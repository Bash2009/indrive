<?php

function signup_user($conn, $data) {
    $first_name = trim($data['first_name'] ?? '');
    $last_name  = trim($data['last_name'] ?? '');
    $email      = trim($data['email'] ?? '');
    $phone      = trim($data['phone'] ?? '');
    $password   = $data['password'] ?? '';
    $confirm    = $data['confirm_password'] ?? '';
    $role       = $data['role'] ?? '';
    $terms      = $data['agree_terms'] ?? '';

    if ($first_name === '' || $last_name === '') {
        return ['ok' => false, 'error' => 'name_required'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }
    if ($phone === '' || !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        return ['ok' => false, 'error' => 'invalid_phone'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'password_short'];
    }
    if ($password !== $confirm) {
        return ['ok' => false, 'error' => 'passwords_mismatch'];
    }
    if ($terms !== '1') {
        return ['ok' => false, 'error' => 'terms_required'];
    }
    if (!in_array($role, ['rider', 'driver'], true)) {
        return ['ok' => false, 'error' => 'invalid_role'];
    }

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['ok' => false, 'error' => 'email_exists'];
    }
    $stmt->close();

    $stmt = $conn->prepare('SELECT id FROM users WHERE phone = ?');
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['ok' => false, 'error' => 'phone_exists'];
    }
    $stmt->close();

    if ($role === 'driver') {
        $license_number = trim($data['license_number'] ?? '');
        $license_expiry = $data['license_expiry'] ?? '';
        $make           = trim($data['vehicle_make'] ?? '');
        $model          = trim($data['vehicle_model'] ?? '');
        $year           = $data['vehicle_year'] ?? '';
        $color          = trim($data['vehicle_color'] ?? '');
        $license_plate  = trim($data['license_plate'] ?? '');
        $vehicle_type   = $data['vehicle_type'] ?? '';

        if ($license_number === '') {
            return ['ok' => false, 'error' => 'license_required'];
        }
        if ($license_expiry === '' || strtotime($license_expiry) === false || strtotime($license_expiry) < time()) {
            return ['ok' => false, 'error' => 'license_expiry_invalid'];
        }
        if ($make === '' || $model === '' || $year === '' || $color === '' || $license_plate === '') {
            return ['ok' => false, 'error' => 'vehicle_required'];
        }
        if (!in_array($vehicle_type, ['economy', 'comfort', 'premium'], true)) {
            return ['ok' => false, 'error' => 'invalid_vehicle_type'];
        }

        $stmt = $conn->prepare('SELECT id FROM vehicles WHERE license_plate = ?');
        $stmt->bind_param('s', $license_plate);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return ['ok' => false, 'error' => 'plate_exists'];
        }
        $stmt->close();
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('INSERT INTO users (first_name, last_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $first_name, $last_name, $email, $phone, $password_hash, $role);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'signup_failed'];
    }
    $user_id = $conn->insert_id;
    $stmt->close();

    if ($role === 'driver') {
        $license_number = trim($data['license_number']);
        $license_expiry = $data['license_expiry'];
        $make           = trim($data['vehicle_make']);
        $model          = trim($data['vehicle_model']);
        $year           = intval($data['vehicle_year']);
        $color          = trim($data['vehicle_color']);
        $license_plate  = trim($data['license_plate']);
        $vehicle_type   = $data['vehicle_type'];

        $stmt = $conn->prepare('INSERT INTO driver_profiles (user_id, license_number, license_expiry) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user_id, $license_number, $license_expiry);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['ok' => false, 'error' => 'signup_failed'];
        }
        $driver_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare('INSERT INTO vehicles (driver_id, make, model, year, color, license_plate, vehicle_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ississs', $driver_id, $make, $model, $year, $color, $license_plate, $vehicle_type);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['ok' => false, 'error' => 'signup_failed'];
        }
        $stmt->close();
    }

    return ['ok' => true, 'user_id' => $user_id];
}

function login_user($conn, $email, $password) {
    $email = trim($email);

    if ($email === '' || $password === '') {
        return ['error' => 'invalid_credentials'];
    }

    $stmt = $conn->prepare('SELECT id, first_name, last_name, email, password_hash, role, is_active FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['error' => 'invalid_credentials'];
    }
    if (!$user['is_active']) {
        return ['error' => 'account_disabled'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['error' => 'invalid_credentials'];
    }

    unset($user['password_hash']);
    return $user;
}

function logout_user() {
    session_destroy();
    header('Location: /index.php');
    exit;
}
