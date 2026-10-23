<?php
const SOURCE_REPOSITORY_URL='https://github.com/austinoehlerking/LLM-Giving';
function source_repository_url(): string {
    return setting('source_url') ?: SOURCE_REPOSITORY_URL;
}
function source_repository_label(): string {
    return strtolower((string)parse_url(source_repository_url(), PHP_URL_HOST)) === 'github.com' ? 'View code on GitHub' : 'View source code';
}
const HOME_TITLE='Folding@home, but for AI usage.';
const HOME_INTRO='Folding@home lets people donate unused computing power to advance scientific research. LLM.Giving brings that same concept to AI.';
function home_metadata(): array {
    $base=rtrim(env('APP_URL','http://127.0.0.1:8086'),'/');
    $description='Put your spare AI usage toward volunteer research with LLM.Giving. Investigate questions, share open-source findings, and independently review the evidence.';
    return [
        'title'=>'Spare AI Usage for Volunteer Research',
        'description'=>$description,
        'canonical'=>$base.'/',
        'socialType'=>'website',
        'socialImage'=>$base.'/assets/llm-giving-social-v1.jpg?v=3',
        'socialImageAlt'=>'LLM.Giving: Folding@home, but for AI usage. Put your spare AI usage to help open-source research.',
        'structuredData'=>['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'LLM.Giving','url'=>$base.'/','description'=>$description],
    ];
}
function research_excerpt(string $text,int $length=155): string {
    $text=preg_replace('/\\s+/u',' ',trim($text));
    preg_match('/^.{0,'.$length.'}/us',$text,$match);
    return ($match[0]??'').(strlen($text)>strlen($match[0]??'')?'…':'');
}

function render(string $view,array $data=[]): void {
    global $path;
    $bufferLevel = ob_get_level();
    extract($data, EXTR_SKIP);
    try {
        ob_start();
        require ROOT.'/views/'.$view.'.php';
        $content = ob_get_clean();
        ob_start();
        require ROOT.'/views/layout.php';
        echo ob_get_clean();
    } catch (Throwable $error) {
        while (ob_get_level() > $bufferLevel) ob_end_clean();
        throw $error;
    }
}
/** The emergency page must work without settings, database access or user content. */
function render_error(string $title, string $message): void {
    while (ob_get_level() > 0) ob_end_clean();
    require ROOT.'/views/error.php';
}
function redirect(string $url): never { header('Location: '.$url,true,303); exit; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function button(string $label,string $href,string $class='button'): string { return '<a class="'.e($class).'" href="'.e($href).'">'.e($label).'</a>'; }
function field(string $label,string $name,string $value='',string $type='text',bool $required=false): void {
    static $index=0;
    $id='f-'.str_replace(['[',']'],['-',''],$name).'-'.(++$index);
    echo '<label for="'.e($id).'">'.e($label).($required?' <span aria-hidden="true">*</span>':'').'</label>';
    if($type==='textarea') echo '<textarea id="'.e($id).'" name="'.e($name).'" '.($required?'required':'').'>'.e($value).'</textarea>';
    else echo '<input id="'.e($id).'" name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.($required?'required':'').'>';
}
function select_field(string $label,string $name,array $options,string $selected='',bool $required=false): void {
    static $index=0; $id='select-'.$name.'-'.(++$index);
    echo '<label for="'.e($id).'">'.e($label).($required?' <span aria-hidden="true">*</span>':'').'</label><select id="'.e($id).'" name="'.e($name).'" '.($required?'required':'').'>';
    foreach($options as $k=>$v) echo '<option value="'.e($k).'" '.((string)$k===$selected?'selected':'').'>'.e($v).'</option>';
    echo '</select>';
}
function terms(bool $researchLicense=false): void {
    echo '<label class="check"><input type="checkbox" name="terms" value="1" required> <span>I have authority to submit this material and accept the <a href="/legal/terms" target="_blank" rel="noopener">public-content terms</a>'.($researchLicense?' and <a href="/legal/licensing" target="_blank" rel="noopener">CC BY 4.0 license for eligible original research (credit contributors and LLM.Giving)</a>':'').'. I understand approved content is public. Third-party rights remain unchanged.</span></label>';
}
function author_fields(): void {
    field('Display name or pseudonym','display_name'); field('LinkedIn URL','linkedin','','url'); field('GitHub URL','github','','url'); field('Website URL','website','','url');
}
function status_badge(string $status): string { if(str_contains($status,'Awaiting')||in_array($status,['Quarantined','Rejected'],true))return '<span class="influence-badge">'.e($status).'</span>'; return '<span class="status '.($status==='Solved'?'solved':(str_contains($status,'Mixed')?'mixed':'')).'">'.e($status).'</span>'; }
function prose(string $text): string { return '<div class="prose">'.nl2br(e($text)).'</div>'; }
function profile_links(string $json): void {
    foreach(json_decode($json,true)??[] as $label=>$url) echo '<a href="'.e($url).'" rel="nofollow noopener noreferrer" target="_blank">'.e(ucfirst($label)).'</a> ';
}
function moderation_form(string $type,string $id,string $state,string $back): void {
    echo '<form method="post" action="/admin/moderate" class="moderate">'.csrf().'<input type="hidden" name="type" value="'.e($type).'"><input type="hidden" name="id" value="'.e($id).'"><input type="hidden" name="back" value="'.e($back).'">';
    select_field('Moderation decision','state',array_combine(MODERATION,MODERATION),$state);
    field('Decision reason (private)','reason','','textarea',true);
    if($type==='contribution') field('Explicit metadata exception rationale, if needed','exception');
    echo '<button>Save moderation decision</button></form>';
}
