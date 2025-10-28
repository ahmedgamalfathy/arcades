<?php

namespace App\Http\Resources\Daily;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Expense\ExpenseResource;
use App\Http\Resources\Timer\SessionDevice\Daily\AllSessionDailyResource;
use App\Http\Resources\Order\Daily\AllOrderDailyResource;
class DailyEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'dailyId' => $this->id,
            // 'startDateTime' => $this->start_date_time,
            // 'endDateTime' => $this->end_date_time??"",
            'totalIncome' => $this->total_income??"",
            'totalExpense' => $this->total_expense??"",
            'totalProfit' => $this->total_profit??"",
            'sessions'=>[
                'total'=>$this->sessions->sum(function($session){
                    return $session->bookedDevices->sum('period_cost');
                }),
                'data'=>AllSessionDailyResource::collection($this->sessions)
            ],
            'orders'=>[
                 'total'=>$this->totalOrders(),
                 'data'=>AllOrderDailyResource::collection($this->orders)
            ],
            'expenses'=>[
                 'total'=>$this->totalExpenses(),
                 'data'=>ExpenseResource::collection($this->expenses)   
            ],

        ];
    }
}
