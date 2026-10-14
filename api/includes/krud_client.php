<?php

function krudApiRequest($endpoint, $payload)
{
    $allowedEndpoints = ['krud.php', 'list.php'];
    if (!in_array($endpoint, $allowedEndpoints, true)) {
        return ['success' => false, 'error' => 'Endpoint API không hợp lệ'];
    }

    $url = rtrim(KRUD_API_URL, '/') . '/' . $endpoint;
    $headers = ['Content-Type: application/json'];
    if (defined('KRUD_TENANT_ORIGIN') && KRUD_TENANT_ORIGIN !== '') {
        $headers[] = 'Origin: ' . rtrim(KRUD_TENANT_ORIGIN, '/');
    }
    $handle = curl_init($url);

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $responseBody = curl_exec($handle);
    $httpStatus = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($responseBody === false) {
        return ['success' => false, 'error' => 'Không thể kết nối KRUD API: ' . $curlError];
    }

    $response = json_decode($responseBody, true);
    if (!is_array($response)) {
        return ['success' => false, 'error' => 'KRUD API trả về dữ liệu không hợp lệ'];
    }

    if ($httpStatus < 200 || $httpStatus >= 300) {
        return [
            'success' => false,
            'error' => $response['error'] ?? ('KRUD API trả HTTP ' . $httpStatus),
        ];
    }

    return $response;
}

function krudApiList($table, $where = [], $sort = [], $page = 1, $limit = 100)
{
    return krudApiRequest('list.php', [
        'table' => $table,
        'page' => $page,
        'limit' => $limit,
        'sort' => $sort,
        'where' => array_values($where),
    ]);
}

function krudApiGet($table, $id)
{
    $result = krudApiList($table, [
        ['field' => 'id', 'operator' => '=', 'value' => (int) $id],
    ], [], 1, 1);
    if (!($result['success'] ?? false)) {
        return $result;
    }

    $record = $result['data'][0] ?? null;
    if (!$record) {
        return ['success' => false, 'error' => 'Không tìm thấy dữ liệu.', 'data' => null];
    }

    return ['success' => true, 'data' => $record];
}

function krudApi($action, $table, $data = [], $id = null)
{
    foreach ($data as $field => $value) {
        if (!is_array($value) || !array_key_exists('value', $value)) {
            $type = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : (is_float($value) ? 'decimal' : 'string'));
            $data[$field] = ['value' => $value, 'type' => $type];
        }
    }

    $payload = [
        'action' => $action,
        'table' => $table,
        'data' => $data,
    ];
    if ($id !== null) {
        $payload['id'] = $id;
    }

    return krudApiRequest('krud.php', $payload);
}

function krudCustomerName($customer)
{
    return $customer['company_name'] ?? $customer['company'] ?? $customer['full_name'] ?? '';
}

function krudCustomerRecord($customer)
{
    $customer['company_name'] = krudCustomerName($customer);
    $customer['status'] = $customer['status'] ?? 'active';
    foreach (['customer_code', 'address', 'phone', 'tax_code', 'email', 'representative', 'contract_date', 'contract_expiry_date', 'frequency'] as $field) {
        $customer[$field] = $customer[$field] ?? '';
    }
    return $customer;
}

function krudCustomerData($data)
{
    if (array_key_exists('company_name', $data)) {
        $data['full_name'] = $data['company_name'];
        unset($data['company_name']);
    }
    unset($data['status']);
    if (empty($data['created_date'])) {
        $data['created_date'] = date('Y-m-d');
    }
    return $data;
}