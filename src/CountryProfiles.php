<?php
declare(strict_types=1);

/** ISO market matrix for the product's 195 UN-member-state markets. */
function country_profiles(): array
{
    static $profiles = null;
    if ($profiles !== null) return $profiles;
    $codes = preg_split('/\s+/', 'AF AL DZ AD AO AG AR AM AU AT AZ BS BH BD BB BY BE BZ BJ BT BO BA BW BR BN BG BF BI CV KH CM CA CF TD CL CN CO KM CG CD CR CI HR CU CY CZ DK DJ DM DO EC EG SV GQ ER EE SZ ET FJ FI FR GA GM GE DE GH GR GD GT GN GW GY HT HN HU IS IN ID IR IQ IE IL IT JM JP JO KZ KE KI KP KR KW KG LA LV LB LS LR LY LI LT LU MG MW MY MV ML MT MH MR MU MX FM MD MC MN ME MA MZ MM NA NR NP NL NZ NI NE NG MK NO OM PK PW PA PG PY PE PH PL PT QA RO RU RW KN LC VC WS SM ST SA SN RS SC SL SG SK SI SB SO ZA SS ES LK SD SR SE CH SY TJ TZ TH TL TG TO TT TN TR TM TV UG UA AE GB US UY UZ VU VE VN YE ZM ZW PS VA');
    $profiles=[];
    foreach ($codes as $code) $profiles[$code]=['code'=>$code,'currency'=>'USD','timezone'=>'UTC','date_format'=>'Y-m-d','phone_prefix'=>''];
    foreach (['TR'=>['TRY','Europe/Istanbul','d.m.Y','+90'],'GB'=>['GBP','Europe/London','d/m/Y','+44'],'US'=>['USD','America/New_York','m/d/Y','+1'],'DE'=>['EUR','Europe/Berlin','d.m.Y','+49'],'FR'=>['EUR','Europe/Paris','d/m/Y','+33'],'ES'=>['EUR','Europe/Madrid','d/m/Y','+34'],'IT'=>['EUR','Europe/Rome','d/m/Y','+39'],'PT'=>['EUR','Europe/Lisbon','d/m/Y','+351'],'NL'=>['EUR','Europe/Amsterdam','d-m-Y','+31'],'RU'=>['RUB','Europe/Moscow','d.m.Y','+7'],'JP'=>['JPY','Asia/Tokyo','Y/m/d','+81'],'CN'=>['CNY','Asia/Shanghai','Y-m-d','+86'],'KR'=>['KRW','Asia/Seoul','Y. m. d.','+82'],'AE'=>['AED','Asia/Dubai','d/m/Y','+971'],'AU'=>['AUD','Australia/Sydney','d/m/Y','+61'],'CA'=>['CAD','America/Toronto','Y-m-d','+1'],'BR'=>['BRL','America/Sao_Paulo','d/m/Y','+55']] as $code=>$values) if(isset($profiles[$code])) [$profiles[$code]['currency'],$profiles[$code]['timezone'],$profiles[$code]['date_format'],$profiles[$code]['phone_prefix']]=$values;
    return $profiles;
}

function country_profile(string $code): array { $profiles=country_profiles(); $code=strtoupper($code); if(!isset($profiles[$code])) throw new InvalidArgumentException('Unsupported country profile.'); return $profiles[$code]; }
