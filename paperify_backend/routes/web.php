<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('subscriptions', [AuthController::class, 'subscriptions']);

Route::get('/', function () {



    //    return view('welcome');
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
    Route::get('report-by-uid', function () {
        return view('report-by-uid');
    });
    Route::post('get-report', function (Request $request) {
        $request->validate([
            'uid' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after_or_equal:start_datetime',
        ]);

        $uid = $request->uid;
        $start = $request->start_datetime;
        $end = $request->end_datetime;

        // Join messages with users
        $messages = DB::table('messages')
            ->join('users', 'messages.user_id', '=', 'users.id')
            ->where('users.uid', $uid)->where('messages.role', 'user')->where('is_weekly_free_usage', 0)
            ->whereBetween('messages.created_at', [$start, $end])
            ->select('users.email', 'messages.created_at as time', 'messages.text')
            ->orderBy('messages.created_at')
            ->get();

        // Create CSV response
        $response = new StreamedResponse(function () use ($messages) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['email', 'time', 'message']);

            foreach ($messages as $message) {
                fputcsv($handle, [$message->email, $message->time, str_split($message->text, 20)[0]]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="messages_export.csv"');

        return $response;
    });
});
