<?php
namespace App\Services\Expense;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Models\Expense\Expense;

class ExpenseService {
 public function allExpenses(Request $request,int $type) {
    $perPage =$request->query('perPage',10);
    $expenses = Expense::where('type',$type)->cursorPaginate($perPage);
    return $expenses;
 }
 public function editExpense(int $id){
    $expense = Expense::find($id);
    if(!$expense){
      throw new ModelNotFoundException("Expense with id {$id} not found");
    }
    return $expense;
 }
 public function createExpense(array $data){
    $expense = Expense::create([
        'user_id'=>auth('api')->id(),
        'name'=>$data['name'],
        'type'=>$data['type'],
        'price'=>$data['price'],
        'date'=>$data['date'],
        'note'=>$data['note']??null
    ]);
    return $expense;
 }
 public function updateExpense(int $id , array $data){
    $expense = Expense::find($id);
    if(!$expense){
      throw new ModelNotFoundException("Expense with id {$id} not found");
    }
    $expense->user_id = auth('api')->id();
    $expense->type = $expense->type;
    $expense->name = $data['name'];
    $expense->price = $data['price'];
    $expense->date = $data['date'];
    $expense->note = $data['note']??null;
    $expense->save();
    return $expense;
 }
 public  function deleteExpense(int $id){
    $expense = Expense::find($id);
    if(!$expense){
      throw new ModelNotFoundException("Expense with id {$id} not found");
    }
    $expense->delete();
    return  $expense;

 }
public function restoreExpense($id)
{
    $expense = Expense::withTrashed()->findOrFail($id);
    if(!$expense){
      throw new ModelNotFoundException("Expense with id {$id} not found");
    }
    $expense->restore();
}

public function forceDeleteExpense($id)
{
    $expense = Expense::withTrashed()->findOrFail($id);
    if(!$expense){
      throw new ModelNotFoundException("Expense with id {$id} not found");
    }
    $expense->forceDelete();
}
}
