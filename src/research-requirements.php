<?php
const RQ_EFFORTS=['low','medium','high','xhigh','max','ultra'];
function rq_requirements(array $r): array {
    $v=json_decode($r['ai_requirements']??'{}',true)??[];
    // Do not rewrite historical records or immutable snapshots.
    return ($v['version']??null)===2?$v:$v+['model'=>'','mode'=>'none','effort'=>''];
}
/** JSON object key order is storage detail; lists, scalar types and every value remain exact. */
function rq_same_requirements(mixed $captured,mixed $current): bool {
    if (!is_array($captured) || !is_array($current) || array_is_list($captured) || array_is_list($current)) return false;
    $canonical = static function(array $value) use (&$canonical): array {
        foreach ($value as $key=>$item) if (is_array($item)) $value[$key]=$canonical($item);
        if (!array_is_list($value)) ksort($value,SORT_STRING);
        return $value;
    };
    return $canonical($captured)===$canonical($current);
}
