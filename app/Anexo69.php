<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Anexo69 extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $table = '69';
    protected $dates = ['fecha_sat','fecha_dof'];
    protected $fillable = [
        'rfc',
        'contribuyente',
        'tipo',
        'oficio',
        'fecha_sat',
        'fecha_dof',
        'url_oficio',
        'url_anexo',
    ];
    public $timestamps = false;
/*    public function newQuery($tipo) {
        $query = parent::newQuery();
        $query->where('tipo', $tipo);
        return $query;
    }*/
}
