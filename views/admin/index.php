<?php
/* [AI:GPT-5.6 Sol | 2026-10-05 UTC] */

// path: /user/modules/contact/views/admin/index.php
// Canonical ChAoS MVC Admin view entry point.
// The established Contact presentation currently names the canonical
// `invalid` lifecycle state `error`; translate only for presentation so an
// invalid schema remains fail-closed and cannot enter normal Admin controls.
if (($data['database_state'] ?? '') === 'invalid') {
    $data['database_state'] = 'error';
}

require __DIR__ . '/contact.php';

/* [End AI:GPT-5.6 Sol] */
