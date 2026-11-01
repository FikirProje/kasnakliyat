#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Reports missing editorial locale versions and, only with --queue, creates
 * review-required AI localization jobs. It never publishes or overwrites text.
 *
 * Examples:
 *   php bin/locale-coverage.php
 *   php bin/locale-coverage.php --queue --limit=20
 *   php bin/locale-coverage.php --queue --languages=de,fr,ar
 */
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') exit(1);

$options=[];
foreach (array_slice($argv,1) as $arg) {
    if ($arg==='--queue') $options['queue']=true;
    elseif (preg_match('/^--([a-z-]+)=(.*)$/D',$arg,$m)) $options[$m[1]]=$m[2];
    else { fwrite(STDERR,"Unknown option: $arg\n"); exit(2); }
}
try {
    migrate();
    $languages=locales();
    if (isset($options['languages'])) {
        $requested=array_filter(array_map('trim',explode(',',$options['languages'])));
        foreach($requested as $code) if(!isset($languages[$code])) throw new InvalidArgumentException("Unknown published locale: $code");
        $languages=array_intersect_key($languages,array_flip($requested));
    }
    $limit=max(1,min(100,(int)($options['limit']??100)));
    $missing=[];
    foreach(rows('SELECT id,source_lang,kind FROM articles ORDER BY id') as $article) {
        $source=row('SELECT title,body FROM translations WHERE article_id=? AND lang=?',[$article['id'],$article['source_lang']]);
        if(!$source || trim((string)$source['body'])==='') continue;
        foreach($languages as $code=>$name) {
            if($code===$article['source_lang'] || row('SELECT id FROM translations WHERE article_id=? AND lang=?',[$article['id'],$code])) continue;
            if(row("SELECT id FROM jobs WHERE article_id=? AND lang=? AND mode='localize' AND status IN ('queued','running')",[$article['id'],$code])) continue;
            $missing[]=['article_id'=>(int)$article['id'],'kind'=>$article['kind'],'source_lang'=>$article['source_lang'],'lang'=>$code,'language'=>$name];
        }
    }
    $report=['mode'=>isset($options['queue'])?'queue':'report','missing'=>count($missing),'items'=>array_slice($missing,0,$limit),'queued'=>0];
    if(isset($options['queue'])) {
        $available=100-(int)(row("SELECT count(*) n FROM jobs WHERE status IN ('queued','running')")['n']??0);
        $take=array_slice($missing,0,min($limit,max(0,$available)));
        db()->beginTransaction();
        try {
            foreach($take as $item) {
                enqueue_ai_job($item['article_id'],$item['lang'],'localize','Create a faithful, natural localization of the approved source. Preserve factual scope. This is a review-required draft; do not publish it.');
                $report['queued']++;
            }
            db()->commit();
        } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    }
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
