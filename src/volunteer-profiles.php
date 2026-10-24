<?php
/** Additional session permission, not evidence of provider capabilities or a technical sandbox. */
const VS_PROFILES = [
    'general'=>[
        'label'=>'General volunteer session',
        'allowed'=>['Work allowed by the existing request, assignment, actual worker configuration and volunteer consent.'],
        'excluded'=>['Unapproved purchases, installation, unrelated accounts/files or work outside the authorized assignment.'],
        'enforcement'=>'Existing server authorization, model, visibility, reservation and evidence rules apply. Actual external resource use is self-reported.',
        'compatibility'=>'Use an eligible AI with actual browser control; selecting a profile does not establish provider support.'
    ],
    'restricted_web_v1'=>[
        'label'=>'Restricted web research',
        'allowed'=>['Web search and reading accessible source pages.','Research synthesis, comparison and reasoning.','Simple model-derived arithmetic explained directly without executing tools.','Ordinary LLM.Giving browser controls for pickup, renewal/release, checking, submission and receipt inspection.'],
        'excluded'=>['Shell/code execution, Python, R, notebooks, calculators, spreadsheets, simulations and scientific software.','Research-tool installation, software construction, submitted-code execution and computational reproduction.','Desktop control, personal-file access, purchases and API-funded substitutes.'],
        'enforcement'=>'The server filters known incompatible requirements and rejects structured execution/build/reproduction records. External tool restrictions are configuration/instructions, not a technical sandbox or provider attestation.',
        'compatibility'=>'Check the actual provider browser and model capabilities. The Dot guide distinguishes its verified single-task browser result from untested phone and multi-task operation.'
    ],
];
function vs_profile_definition(string $id): array {
    return VS_PROFILES[$id]??fail(422,'Choose an available volunteer capability profile.');
}
function vs_profile_id(array $session): string {
    $id=$session['session_profile']??'general';
    if (!is_string($id) || !isset(VS_PROFILES[$id])) fail(403,'The recorded session profile is not recognized. Fresh authorization is required.');
    if (isset($session['consent']) && ($session['consent']['session_profile']??'general')!==$id) fail(403,'Session profile differs from the captured authorization. Fresh authorization is required.');
    return $id;
}
function vs_profile_compatible(array $session,array $research,array $task): bool {
    if (vs_profile_id($session)==='general') return true;
    $external=external_requirements($research);
    if (rt_is_tool($research) || !empty($task['builds_tool']) || !empty($task['computational']) || calculation_policy($research)['required']) return false;
    $software=trim($external['required_software']??'');
    $ordinaryWeb=in_array(strtolower($software),['browser','web browser','web search','web search and browser'],true);
    // An ordinary browser/search requirement is within this profile; plugins, named versions and unknown packages are not inferred.
    if (in_array($external['external_tools'],['licensed','mixed'],true) || ($external['external_tools']==='free' && !$ordinaryWeb) || ($software!=='' && !$ordinaryWeb)) return false;
    // The legacy execution-review schema requires actual reproduction, not merely reading a result.
    if ($task['kind']==='review' && (rc_targets($task) || rt_targets($task))) return false;
    return true;
}
function vs_profile_assert_packet(array $session,array $packet): void {
    if (vs_profile_id($session)==='general') return;
    $evidence=$packet['evidence']??[];
    if (!is_array($evidence)) return; // The normal evidence validator reports malformed payloads.
    if ((!empty($evidence['derivations']) || !empty($evidence['derivation_reviews'])) && !function_exists('rd_validate')) fail(503,'Model-derived arithmetic validation is unavailable. Preserve the work; nothing was recorded. Do not replace it with fabricated execution evidence.');
    foreach (['calculations','calculation_checkpoint','calculation_reviews','tool_builds','tool_reviews'] as $field) {
        if (!empty($evidence[$field])) fail(422,'Restricted web research cannot submit executed calculations, tool builds or computational reproduction. Use the honest model-derived arithmetic schema for work explained without tools. Preserve the work and explicitly release/skip an incompatible assignment; lack of this worker capability is not a scientific project blocker.');
    }
}
function vs_profile_instructions(array $session): string {
    $id=vs_profile_id($session);$profile=vs_profile_definition($id);
    if ($id==='general') return '';
    return "\nAUTHORIZED CAPABILITY PROFILE: ".$id.' — '.$profile['label']."\nAllowed: ".implode(' ',$profile['allowed'])."\nExcluded: ".implode(' ',$profile['excluded'])."\n".$profile['enforcement']."\n".$profile['compatibility']."\nBefore research, inspect the complete deliverable, supporting material and concrete dependencies. If hidden requirements need excluded tools, explicitly release the reservation and skip honestly through the session controls. Do not submit a scientific blocked/needs_clarification outcome merely because this worker lacks tools. Do not disguise code execution as model reasoning, invent files/software/checksums, or claim computational reproduction. Preserve genuinely useful partial work only when its evidence can be submitted honestly within this profile. Use evidence.derivations (version=1,type=model_derived) and evidence.derivation_reviews, never execution calculations or reproduction reviews. Model-derived arithmetic must retain cited inputs, units, formula, worked steps, assumptions and limitations and state that it was not computationally executed or reproduced. If interactive browser control is unavailable, report NOT SUBMITTED and the exact capability gap. Do not install browser tooling, substitute HTTP fetching or desktop/Work, or claim phone/Dot acceptance from a fallback. Provider/runtime settings must actually support the allowed work; unknown required model/reasoning settings are unconfirmed, not a match.\n";
}
