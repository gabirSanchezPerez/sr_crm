<?php
namespace App\Models;
use CodeIgniter\Model;
final class ProposalPaymentModel extends Model { protected $table='propuesta_pago';protected $primaryKey='id';protected $returnType='array';protected $useTimestamps=false;protected $allowedFields=['propuesta_id','fecha_pago','monto','secuencia','u_crea','u_modifica','f_creacion','f_modificacion','deleted'];public function activeForProposal(int $id):array{return $this->where('propuesta_id',$id)->where('deleted',0)->orderBy('secuencia')->findAll();}}
