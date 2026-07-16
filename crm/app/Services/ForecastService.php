<?php
namespace App\Services;
final class ForecastService
{
 public function annual(int $year,array $identity):array
 { $previous=$this->actual($year-1,$identity);$actual=$this->actual($year,$identity);$goal=$this->goals($year,$identity);$ta=array_sum($actual);$tg=array_sum($goal);return ['year'=>$year,'labels'=>['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],'previous'=>$previous,'actual'=>$actual,'goal'=>$goal,'totals'=>['previous'=>array_sum($previous),'actual'=>$ta,'goal'=>$tg,'attainment'=>$tg>0?round($ta/$tg*100,1):0]]; }
 private function actual(int $year,array $identity):array
 { $out=array_fill(0,12,0.0);$db=db_connect();if(!$db->tableExists('propuesta_pago'))return $out;$p=(int)($identity['perfil_id']??0);$b=$db->table('propuesta_pago pp')->select('pp.fecha_pago,pp.monto')->join('propuesta p','p.id=pp.propuesta_id AND p.deleted=0 AND p.estado_id=4','inner')->where('pp.deleted',0)->where('pp.fecha_pago >=',$year.'-01-01')->where('pp.fecha_pago <',($year+1).'-01-01');if($p===2)$b->join('usuario_ucomercial x','x.usuario_id=p.ejecutivo_id AND x.deleted=0','inner')->where('x.ucomercial_id',(int)($identity['ucomercial_id']??0));elseif($p===3)$b->where('p.ejecutivo_id',(int)($identity['user_id']??0));elseif($p!==1)$b->where('1 =',0,false);foreach($b->get()->getResultArray() as $r){$m=(int)substr((string)$r['fecha_pago'],5,2);if($m>=1&&$m<=12)$out[$m-1]+=(float)$r['monto'];}return $out; }
 private function goals(int $year,array $identity):array
 { $out=array_fill(0,12,0.0);$db=db_connect();if(!$db->tableExists('meta_venta'))return $out;$p=(int)($identity['perfil_id']??0);$b=$db->table('meta_venta m')->select('m.mes,m.monto')->join('usuario u','u.id=m.usuario_id AND u.deleted=0','inner')->where(['m.anio'=>$year,'m.deleted'=>0]);if($p===1)$b->where('u.perfil_id',2);elseif($p===2)$b->where('u.perfil_id',3)->where('m.ucomercial_id',(int)($identity['ucomercial_id']??0));elseif($p===3)$b->where('m.usuario_id',(int)($identity['user_id']??0));else $b->where('1 =',0,false);foreach($b->get()->getResultArray() as $r){$m=(int)$r['mes'];if($m>=1&&$m<=12)$out[$m-1]+=(float)$r['monto'];}return $out; }
}
