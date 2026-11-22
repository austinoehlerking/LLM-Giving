<?php
/** Scope, capability profile and limits only. Existing sessions keep their original records. */
function vs_setup_defaults(array $draft): array {
    $custom=isset($draft['minutes'])||isset($draft['assignment_limit']);
    $preset=$draft['session_preset']??($custom?'custom':VS_DEFAULT_PRESET);
    $limits=isset(VS_PRESETS[$preset]['minutes'])?VS_PRESETS[$preset]:VS_PRESETS['standard'];
    $draft+=['session_preset'=>$preset,'minutes'=>(string)$limits['minutes'],'assignment_limit'=>(string)$limits['tasks'],'scope_kind'=>'all','session_profile'=>'general','concurrent_limit'=>'1'];
    return $draft;
}
