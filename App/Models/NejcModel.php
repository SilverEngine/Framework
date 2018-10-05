<?php
namespace App\Models;

use Silver\Core\Model;
/**
 *
 */
class nejcModel extends Model
{

    protected $table = 'nejc';
    protected $primaryKey = 'id';

    protected $selectable = [

    ];

    protected $fillable = [

    ];

    protected $filterable = [

    ];

    protected $includable = [

    ];

    protected $hidden = [

    ];

    public function getnejcs()
    {
        return $this->select('nejc')->all();
    }
}
