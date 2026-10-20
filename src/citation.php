<?php
const RESEARCH_LICENSE='CC-BY-4.0';
const RESEARCH_LICENSE_URL='https://creativecommons.org/licenses/by/4.0/';
function research_citation(array $r,?array $result=null): string {
    $author=trim($result['display_name']??$r['display_name']??'')?:'LLM.Giving contributors';
    $date=$result['created_at']??$r['created_at'];
    $url=rtrim(env('APP_URL'),'/').'/research/'.$r['slug'];
    $version='Research request';
    if($result){$url.='/results/'.$result['id'];$version='Research result '.$result['id'];}
    return $author.'. ('.$date.' UTC). '.$r['title'].'. '.$version.'. LLM.Giving. '.$url.' — CC BY 4.0: '.RESEARCH_LICENSE_URL;
}
function research_license_notice(): string {
    return 'Eligible original research contributions are licensed under CC BY 4.0. When reusing covered material, credit the named contributors and LLM.Giving, link to the specific result and https://creativecommons.org/licenses/by/4.0/, and indicate changes. Cite underlying scientific sources too. Third-party rights and public-domain facts remain separate. Do not imply endorsement.';
}
