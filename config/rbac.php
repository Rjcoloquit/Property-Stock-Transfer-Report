<?php

const PTR_ADMIN_USERNAME = 'admin';
const PTR_ADMIN_PASSWORD = 'publicheadservice';

/**
 * Canonical role label used across pages.
 */
function ptr_current_role(): string
{
    $role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
    if ($role === 'admin') {
        return 'Admin';
    }
    return 'Encoder';
}

function ptr_is_encoder(): bool
{
    return ptr_current_role() === 'Encoder';
}

function ptr_require_login(bool $json = false): void
{
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }

    header('Location: login.php');
    exit;
}

/**
 * Encoder is restricted to these modules only.
 */
function ptr_require_page_access(string $pageKey, bool $json = false): void
{
    if (ptr_current_role() === 'Admin') {
        return;
    }

    $encoderAllowed = [
        'home',
        'create_ptr',
        'pending_transactions',
        'report',
        'notifications',
        'stock_card',
        'current_stock_report',
        'outbound_summary_report',
        'incident_report',
        'item_search_suggest',
        'logout',
    ];

    if (in_array($pageKey, $encoderAllowed, true)) {
        return;
    }

    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }

    header('Location: create_ptr.php?msg=' . urlencode('Access limited to Encoder modules.'));
    exit;
}

/**
 * Encoder cannot perform any edit/delete-style action.
 */
function ptr_block_encoder_mutations(bool $json = false): void
{
    if (!ptr_is_encoder()) {
        return;
    }

    $payload = array_merge($_GET ?? [], $_POST ?? []);
    $candidateKeys = ['action', 'cmd', 'mode', 'intent', 'operation'];
    $forbiddenTerms = ['edit', 'update', 'delete', 'remove'];

    foreach ($candidateKeys as $key) {
        if (!isset($payload[$key])) {
            continue;
        }
        $value = strtolower(trim((string) $payload[$key]));
        foreach ($forbiddenTerms as $term) {
            if ($value !== '' && str_contains($value, $term)) {
                if ($json) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Forbidden action for Encoder role.']);
                    exit;
                }
                header('Location: create_ptr.php?msg=' . urlencode('Encoder role cannot edit or delete records.'));
                exit;
            }
        }
    }
}

