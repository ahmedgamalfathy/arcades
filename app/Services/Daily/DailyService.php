<?php
namespace App\Services\Daily;
use App\Models\Daily\Daily;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filters\Daily\DailySearchFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Timer\DeviceTimerService;
use Spatie\Activitylog\Models\Activity;
use App\Models\Order\Order;
use App\Models\Expense\Expense;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Device\Device;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Http\Resources\ActivityLog\DailyActivityResource;

class DailyService
{
   public function __construct( protected DeviceTimerService $timerService)
   {
   }
    public function allDailies(Request $request)
    {
        $perPage = $request->query('perPage', 10);
        $dailyId = $request->query('dailyId');
        $search = $request->query('search');
        $includes = array_map('trim', explode(',', $request->query('include', '')));
        $includes = array_filter($includes); // إزالة القيم الفارغة

        $query = QueryBuilder::for(Daily::class);

        // إذا كان هناك بحث
        if ($search) {
            $query->where(function($q) use ($search, $includes) {
                if (in_array('orders', $includes)) {
                    $q->orWhereHas('orders', function($orderQuery) use ($search) {
                        $orderQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('number', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%");
                        $orderQuery->orWhereHas('bookedDevice.device', function($deviceQuery) use ($search) {
                            $deviceQuery->where('name', 'like', "%{$search}%");
                        });
                    });
                }
                if (in_array('sessions', $includes)) {
                    $q->orWhereHas('sessions', function($sessionQuery) use ($search) {
                        $sessionQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('bookedDevices.device', function($deviceQuery) use ($search) {
                                $deviceQuery->where('name', 'like', "%{$search}%");
                            });
                    });
                }
                if (in_array('expenses', $includes)) {
                    $q->orWhereHas('expenses', function($expenseQuery) use ($search) {
                        $expenseQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%");
                    });
                }
            });
            foreach ($includes as $include) {
               if ($include === 'orders') {
                    $query->with(['orders' => function($q) use ($search) {
                        $q->where(function($orderQ) use ($search) {
                            $orderQ->where('name', 'like', "%{$search}%")
                                ->orWhere('number', 'like', "%{$search}%")
                                ->orWhere('price', 'like', "%{$search}%");
                        });
                    }]);
               }
                
                if ($include === 'sessions') {
                    $query->with(['sessions' => function($q) use ($search) {
                        $q->where(function($sessionQ) use ($search) {
                            $sessionQ->where('name', 'like', "%{$search}%")
                                ->orWhereHas('bookedDevices.device', function($deviceQuery) use ($search) {
                                    $deviceQuery->where('name', 'like', "%{$search}%");
                                });
                        })->with('bookedDevices.device'); // تحميل الأجهزة أيضاً
                    }]);
                }
                if ($include === 'expenses') {
                    $query->with(['expenses' => function($q) use ($search) {
                        $q->where(function($expenseQ) use ($search) {
                            $expenseQ->where('name', 'like', "%{$search}%")
                                ->orWhere('price', 'like', "%{$search}%");
                        });
                    }]);
                }
            }
        } else {
            // بدون بحث - تحميل العلاقات عادي
            $query->allowedIncludes(['sessions', 'orders', 'expenses']);
        }

        // الفلاتر الأخرى
        $dailies = $query
            ->when($dailyId, function ($q) use ($dailyId) {
                return $q->where('id', $dailyId);
            })
            ->allowedFilters([
                AllowedFilter::scope('has_orders'),
                AllowedFilter::scope('has_expenses'),
                AllowedFilter::scope('has_sessions'),
            ])
            ->cursorPaginate($perPage);

        return $dailies;
    }

     public function editDaily($id)
     {//sessions , orders , expenses
        return Daily::with('sessions','orders','expenses')->findOrFail($id);
     }
     public function createDaily($data)
     {
      $daily =Daily::where('end_date_time',null)->first();
      if($daily){
        throw new ModelNotFoundException('Daily is already open');
      }
      if($data['startDateTime'] == null){
        $data['startDateTime'] = Carbon::now()->format('Y-m-d H:i:s');
      }
        return Daily::create([
            'start_date_time' => $data['startDateTime'],
            'end_date_time' => $data['endDateTime'] ?? null,
            'total_income' => $data['totalIncome'] ?? null,
            'total_expense' => $data['totalExpense'] ?? null,
            'total_profit' => $data['totalProfit'] ?? null,
        ]);
     }
     public function updateDaily($id, $data)
     {
        return Daily::findOrFail($id)->update([
            'start_date_time' => $data['startDateTime'],
            'end_date_time' => $data['endDateTime'] ?? null,
            'total_income' => $data['totalIncome'] ?? null,
            'total_expense' => $data['totalExpense'] ?? null,
            'total_profit' => $data['totalProfit'] ?? null,
        ]);
     }
     public function deleteDaily($id)
     {
        return Daily::findOrFail($id)->delete();
     }
     public function closeDaily()
     {
      $daily =Daily::where('end_date_time',null)->first();
      if(!$daily){
        throw new ModelNotFoundException('Daily is not open');
      }
      $totalBookedDevice =0;
      if($daily->sessions()->count() > 0){
        foreach ($daily->sessions as $session) {
            foreach ($session->bookedDevices as $bookedDevice) {
               $this->timerService->finish($bookedDevice->id);
               $totalBookedDevice += $bookedDevice->period_cost;
            }
        }
      }
      $income =$totalBookedDevice + $daily->totalOrders();
      $daily->end_date_time = Carbon::now()->format('Y-m-d H:i:s');
      $daily->total_income = $income;
      $daily->total_expense = $daily->totalExpenses();
      $daily->total_profit = $income - $daily->totalExpenses();
      $daily->save();
      return $daily;
     }
    public function dailyReport()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $dailies = Daily::query()
            ->with(['orders', 'expenses', 'sessions.bookedDevices'])
            ->whereBetween('start_date_time', [$startOfMonth, $endOfMonth])
            ->orderBy('start_date_time', 'asc')
            ->get()
            ->groupBy(function ($daily) {
                return Carbon::parse($daily->start_date_time)->format('Y-m-d');
            })
            ->map(function ($dailiesPerDay, $date) {
                return [
                    'date' => $date,
                    'orders' => $dailiesPerDay->sum('total_orders'),
                    'devices' => $dailiesPerDay->sum('total_session_devices'),
                    'expenses' => $dailiesPerDay->sum('total_expenses'),
                    // 'count' => $dailiesPerDay->count(), // عدد السجلات لهذا اليوم
                ];
            })
            ->values(); 

        return $dailies;
    }
    public function activityLog($dailyId) 
    {
        $daily = Daily::findOrFail($dailyId);
        $sessionIds = $daily->sessions()->pluck('id')->toArray();
        $orderIds = $daily->orders()->pluck('id')->toArray();
        $expenseIds = $daily->expenses()->pluck('id')->toArray();
        $bookedDeviceIds = $daily->sessions()
            ->with('bookedDevices:id,session_device_id')
            ->get()
            ->flatMap(fn($session) => $session->bookedDevices->pluck('id'))
            ->toArray();

        // ✅ نستخدم whereIn بدلاً من where (لأن القيم مصفوفة)
        $sessions = Activity::whereIn('subject_id', $sessionIds)
            ->where('subject_type', SessionDevice::class)
            ->get();

        $orders = Activity::whereIn('subject_id', $orderIds)
            ->where('subject_type', Order::class)
            ->get();

        $expenses = Activity::whereIn('subject_id', $expenseIds)
            ->where('subject_type', Expense::class)
            ->get();

        $bookedDevices = Activity::whereIn('subject_id', $bookedDeviceIds)
            ->where('subject_type', BookedDevice::class)
            ->get();
        $dailyActivities = Activity::where('subject_id', $dailyId)
            ->where('subject_type', Daily::class)
            ->get(); 


        
            $activities = [
            'daily'=>$dailyActivities,
            'sessions' => $sessions,
            'orders' => $orders,
            'expenses' => $expenses,
            'bookedDevices' => $bookedDevices,
        ];
        return $activities;
    }


}