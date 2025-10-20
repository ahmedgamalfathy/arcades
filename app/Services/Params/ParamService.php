<?php
namespace App\Services\Params;
use Illuminate\Http\Request;
use App\Models\Setting\Param\Param;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ParamService
{
    public function allParams($validated)
    {
        return Param::select('id','type','note')
                ->where('parameter_order', $validated['parameterOrder'])->get();
    }
    public function getParamById(int $id)
    {
        $param= Param::select('id','type','note')->find($id);
        if (!$param){
         throw  new ModelNotFoundException();
        }
        return $param;
    }
    public function createParam(array $data)
    {
        Param::create([
           'type'=>$data['type'],
           'note'=>$data['note']??null,
           'parameter_order'=>$data['parameterOrder']
        ]);
        return "done";
    }
    public function updateParam(int $id,array $data)
    {
        $param = Param::find($id);
        if (!$param) {
            throw new ModelNotFoundException();
        }
        $param->update([
           'type'=>$data['type'],
           'note'=>$data['note']??null,
           'parameter_order'=>$data['parameterOrder']
        ]);
        return "done";
    }
    public function deleteParam(int $id)
    {
        $param = Param::find($id);
        if (!$param) {
            throw new ModelNotFoundException();
        }
        $param->delete();
        return "done";
    }

}
