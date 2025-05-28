<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;
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

    function convertToEnglishDigits($input) {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
        return str_replace($arabic, $english, str_replace($persian, $english, $input));
    }

    $request->merge([
        'uid' => convertToEnglishDigits($request->uid),
        'start_datetime' => convertToEnglishDigits($request->start_datetime),
        'end_datetime' => convertToEnglishDigits($request->end_datetime),
    ]);

    $request->validate([
        'uid' => 'required|string',
        'start_datetime' => 'required|date',
        'end_datetime' => 'required|date|after_or_equal:start_datetime',
    ]);

    $uid = $request->uid;
    $start = $request->start_datetime;
    $end = $request->end_datetime;

    $messages = DB::table('messages')
        ->join('users', 'messages.user_id', '=', 'users.id')
        ->where('users.uid', $uid)
        ->where('messages.role', 'user')
        ->where('is_weekly_free_usage', 0)
        ->whereBetween('messages.created_at', [$start, $end])
        ->select('users.email', 'messages.created_at as time', 'messages.text')
        ->orderBy('messages.created_at')
        ->get();

    $response = new StreamedResponse(function () use ($messages) {
        $handle = fopen('php://output', 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        fputcsv($handle, ['email', 'time', 'message']);

        foreach ($messages as $message) {
            $utcTime = Carbon::parse($message->time)->setTimezone('Asia/Tehran');

            $jalali = Jalalian::fromCarbon($utcTime);
            $formattedDate = $jalali->format('Y/m/d H:i');

            $text = preg_replace_callback('/[۰-۹٠-٩]/u', function ($match) {
                $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
                return $map[$match[0]];
            }, $message->text);

            fputcsv($handle, [$message->email, $formattedDate, $text]);
        }

        fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="messages_export.csv"');

    return $response;
});


});
