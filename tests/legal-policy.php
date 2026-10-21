<?php
require __DIR__.'/bootstrap.php';
require ROOT.'/src/view.php';
$checks = 0;
function policy_check(bool $ok, string $name): void {
    global $checks;
    if (!$ok) throw new RuntimeException($name);
    $checks++;
}

// Isolate all policy edits and acceptance fixtures; never rewrite historical or source files.
db()->beginTransaction();
try {
    $legacy = 'legacy-policy-'.substr(uid(), 0, 12);
    $historical = legal_pages();
    $historical['privacy'] = 'Previously recorded privacy policy.';
    $historicalJson = json_encode($historical);
    insert('legal_versions', ['version'=>$legacy, 'pages'=>$historicalJson, 'created_at'=>now()]);
    set_setting('terms_version', $legacy);
    $override = 'Synthetic existing operator privacy override.';
    set_setting('page_privacy', $override);
    $notice = google_privacy_notice();

    $version = terms_version();
    policy_check($version !== $legacy, 'Existing installations get a new effective version for the disclosure');
    policy_check(strlen($version) <= 40, 'Effective version fits the existing schema');
    policy_check($version === terms_version(), 'Unchanged effective policy has a stable version');
    policy_check(one('SELECT version FROM legal_versions WHERE version=?', [$version]) === null, 'Read-only version lookup does not create a snapshot');

    $path = '/legal/privacy'; $title = 'Privacy'; $body = $override;
    ob_start(); require ROOT.'/views/legal.php'; $html = ob_get_clean();
    $visible = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    policy_check(str_contains($visible, $override), 'Rendered policy retains the operator override');
    policy_check(str_contains($visible, $notice['body']), 'Rendered policy contains the complete shared Analytics notice');
    policy_check(str_contains($html, $version), 'Rendered version identifies the effective disclosure');
    policy_check(one('SELECT version FROM legal_versions WHERE version=?', [$version]) === null, 'Rendering remains read-only');

    $acceptedVersion = terms_version(record: true);
    $recordedJson = one('SELECT pages FROM legal_versions WHERE version=?', [$acceptedVersion])['pages'];
    $recorded = json_decode($recordedJson, true);
    policy_check($acceptedVersion === $version, 'Acceptance records the version displayed to visitors');
    policy_check(str_contains($recorded['privacy'], $override), 'Accepted snapshot retains custom privacy text');
    policy_check(str_contains($recorded['privacy'], $notice['body']), 'Accepted snapshot includes the exact displayed Analytics notice');
    policy_check(str_contains($visible, $notice['body']) && str_contains($recorded['privacy'], $notice['body']), 'Rendered and retained disclosures match');
    policy_check($recorded === legal_pages(), 'Accepted snapshot contains the complete effective bundle');

    $changedNotice = legal_pages();
    $changedNotice['privacy'] .= "\nSynthetic revised Analytics disclosure.";
    policy_check(legal_policy_version($changedNotice, $legacy) !== $version, 'Disclosure changes create a different version without an admin edit');
    save_public_page('privacy', $override."\nUpdated operator policy.");
    $edited = terms_version();
    policy_check($edited !== $version, 'Admin privacy edit creates a new effective version');
    $editedPages = json_decode(one('SELECT pages FROM legal_versions WHERE version=?', [$edited])['pages'], true);
    policy_check(str_contains($editedPages['privacy'], $notice['body']), 'Admin-saved snapshot includes the displayed notice');
    policy_check(one('SELECT pages FROM legal_versions WHERE version=?', [$version])['pages'] === $recordedJson, 'Earlier effective snapshot remains byte-preserved');
    policy_check(one('SELECT pages FROM legal_versions WHERE version=?', [$legacy])['pages'] === $historicalJson, 'Historical pre-upgrade snapshot is never rewritten');

    $category = one('SELECT id FROM categories WHERE enabled=1 ORDER BY id LIMIT 1')['id'];
    $created = create_research(['title'=>'[QA] Complete policy acceptance record', 'question'=>'Does a new request retain the complete effective policy and Analytics disclosure?', 'category_id'=>$category, 'terms'=>1]);
    $requestVersion = one('SELECT terms_version FROM research WHERE id=?', [$created['id']])['terms_version'];
    policy_check($requestVersion === terms_version(), 'New request records the effective acceptance version');
    $requestPages = json_decode(one('SELECT pages FROM legal_versions WHERE version=?', [$requestVersion])['pages'], true);
    policy_check(str_contains($requestPages['privacy'], $notice['body']), 'New request acceptance has a complete retained policy');
} finally {
    if (ob_get_level() > 0) ob_end_clean();
    if (db()->inTransaction()) db()->rollBack();
}
echo $checks." complete policy-version checks passed.\n";