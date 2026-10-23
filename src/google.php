<?php
/** Public Google identifiers, never credentials. Empty environment overrides disable either service. */
function google_measurement_id(): string {
    $id = env('GOOGLE_ANALYTICS_ID', 'G-GV00TT8CG5');
    return preg_match('/^G-[A-Z0-9]{6,20}$/D', $id) ? $id : '';
}
function google_verification_token(): string {
    $token = env('GOOGLE_SITE_VERIFICATION', 'W0RY6n4_jhEro5SSgp_EoNa3J67g8A_rVubDAmrOGUY');
    return preg_match('/^[A-Za-z0-9_-]{20,200}$/D', $token) ? $token : '';
}
function google_analytics_request(): bool {
    if (env('GOOGLE_ANALYTICS_ENABLED', env('APP_ENV') === 'production' ? '1' : '0') !== '1' || google_measurement_id() === '') return false;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !empty($_SESSION['admin']) || !empty($_SESSION['manage']) || !empty($_SESSION['volunteer_sessions'])) return false;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    // A closed route list excludes receipts, management secrets, forms, packets, files and errors.
    return is_string($path) && (bool)preg_match('~^/(?:research|about|contact|blog(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?|legal/(?:terms|privacy|licensing|moderation|copyright)|research/[a-z0-9]+(?:-[a-z0-9]+)*)?$~D', $path);
}
function google_privacy_notice(): array {
    $body = file_get_contents(ROOT.'/content/analytics-privacy.txt');
    if ($body === false) throw new RuntimeException('Analytics privacy notice is unavailable.');
    return ['title' => 'Google Analytics', 'body' => trim($body)];
}
function google_head(string $view): void {
    if ($view === 'home' && google_verification_token() !== '') {
        echo '<meta name="google-site-verification" content="'.e(google_verification_token()).'">';
    }
    if (!google_analytics_request() || http_response_code() >= 300 || !in_array($view, ['home','discovery','research','blog','blog-article','legal'], true)) return;
    echo '<script src="/assets/analytics.js?v=2" data-google-analytics="'.e(google_measurement_id()).'" defer></script>';
}
