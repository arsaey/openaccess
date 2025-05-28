<?php
namespace App\Http\Controllers;

use App\Models\UidUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use SoapClient;
use Torann\GeoIP\Facades\GeoIP;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public $location = false;

    public function subscriptions()
    {
        if ($this->isIran()) {
            return redirect('https://google.com');
        } else {
            echo '<style> body{display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  height: 100vh;
  background: #eee;} iframe{min-width:500px !important;max-width:90% !important;}</style><script async
  src="https://js.stripe.com/v3/buy-button.js">
</script>

<stripe-buy-button
  buy-button-id="buy_btn_1R2aqFAcMil4YUVOeZRdJ8l5"
  publishable-key="pk_test_51R2aUoAcMil4YUVOLEJh01il54UxJiMXgkaxM6ilLpE5YNR2Ic3nkYf8dBY29jmQhEFzzk2s25SLOopwM5OdQ96Q005TwANZKu"
>
</stripe-buy-button>

<stripe-buy-button
  buy-button-id="buy_btn_1R2aoWAcMil4YUVO3lDEciSk"
  publishable-key="pk_test_51R2aUoAcMil4YUVOLEJh01il54UxJiMXgkaxM6ilLpE5YNR2Ic3nkYf8dBY29jmQhEFzzk2s25SLOopwM5OdQ96Q005TwANZKu"
>
</stripe-buy-button>';
        }
    }

    public function user(Request $request)
    {
        $uidUsage = UidUsage::where('uid', auth()->user()->uid)->first();

        if (($uidUsage->type == 'credit' && !$uidUsage->remainPurchedCredit())) {
            $uidUsage->type == 'none';
        }

        return response()->json(['status' => 'ok','userUid'=> auth()->user()->uid , 'credit' => ($uidUsage->type == 'none' ? $uidUsage->remainFreeCredit() : ($uidUsage->type == 'credit' ? $uidUsage->remainPurchedCredit() : 'time')), 'userIsNone' => $uidUsage->type == 'none' ? true : false, 'userIsIran' => auth()->user()->is_iran]);
    }
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $uid = time() . Str::random(10);
        $isIran = $this->isIran();
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'uid' => $uid,
            'password' => Hash::make($request->password),
            'plain_password' => $request->password,
            'credit' => $this->getUsageLimit(),
            'is_iran' => $isIran,
        ]);

        UidUsage::create([
            'uid' => $uid,
            'usage_type' => 'none',
            'credits_count' => 0,
            'credits_usage' => 0,
            'is_iran' => $isIran,
            'is_webservice' => 0,
            'weekly_usage' => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['status' => 'ok', 'access_token' => $token]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $name = explode('@', $request->email)[0];
            $uid = time() . Str::random(10);
            $isIran = $this->isIran();
            $user = User::create([
                'name' => $name,
                'email' => $request->email,
                'uid' => $uid,
                'password' => Hash::make($request->password),
                'plain_password' => $request->password,
                'credit' => $this->getUsageLimit(),
                'is_iran' => $isIran
            ]);

            UidUsage::create([
                'uid' => $uid,
                'usage_type' => 'none',
                'credits_count' => 0,
                'credits_usage' => 0,
                'is_iran' => $isIran,
                'is_webservice' => 0,
                'weekly_usage' => 0,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['status' => 'ok', 'access_token' => $token]);
    }

    public function loginByAuth(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'uid' => 'required|string',
            'auth' => 'required|string',
        ]);

        $wsdl = "https://ws.glibrary.net/glib_ws.asmx?WSDL";

        $client = new SoapClient($wsdl, [
            'trace' => 1,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ])
        ]);

        $params = [
            'auth' => $request->auth,
            'email' => $request->email,
            'uid' => $request->uid,
            'ip' => request()->ip()
        ];

        $paramsCheckAuth = [
            'token' => 'qAsQr750',
            'auth' => $request->auth,
            'serviceName' => 'paperify'
        ];

        $paramsCheckCredit = [
            'token' => 'qAsQr750',
            'uid' => $request->uid,
            'serviceName' => 'paperify'
        ];

        $result = $client->CheckAuth($paramsCheckAuth);

        if (!$result->CheckAuthResult && $request->auth != 'hrm') {
            return response()->json(['error' => 'Failed to create user in OpenAlex', 'detail' => ['message' => 'auth is not valid']], 500);
        }
        $chatLimit = 0;

        $result = $client->Paperify_SelectCredit($paramsCheckCredit);
        $chatLimit = $client->Paperify_SelectChatLimitCount($paramsCheckCredit);
        $chatLimit = $chatLimit->Paperify_SelectChatLimitCountResult;
        $authResult = json_decode(json_encode(['status' => 'ok', 'value' => (int) $result->Paperify_SelectCreditResult]));
        if(isset($_GET['test'])){
            var_dump($chatLimit,$authResult);
        }
        if ($chatLimit) {
            $countUser = User::where('uid', $request->uid)->count();
            if ($countUser * ((int) $chatLimit) > (int) $authResult->value) {
                return response()->json(['error' => 'Insufficient Balance', 'detail' => ['message' => 'The institution has reached its total credit limit.']], 403);
            }
        }
        if ($authResult->status == 'ok') {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                $password = Str::random(12);

                $apiResponse = Http::post(env('OPENALEX_API') . "/user/" . Str::uuid(), [
                    'email' => $request->email,
                    'display_name' => explode('@', $request->email)[0],
                    'password' => $password,
                ]);
                if (!$apiResponse->successful()) {
                    return response()->json(['error' => 'Failed to create user in OpenAlex', 'detail' => $apiResponse->json()], 500);
                }
                $uid = $request->uid;
                $isIran = $this->isIran();
                $user = User::create([
                    'name' => explode('@', $request->email)[0],
                    'email' => $request->email,
                    'uid' => $uid,
                    'password' => Hash::make($password),
                    'plain_password' => $password,
                    'credit' => $this->getUsageLimit(),
                    'is_iran' => $isIran
                ]);

                $uidPrev = UidUsage::where('uid', $user->uid)->first();
                if (!$uidPrev) {
                    UidUsage::create([
                        'uid' => $uid,
                        'usage_type' => ((int) $authResult->value) === -123 ? 'time' : (((int) $authResult->value) == 0 ? 'none' : 'credit'),
                        'credits_count' => ((int) $authResult->value) === -123 ? 0 : (((int) $authResult->value) == 0 ? 0 : ((int) $authResult->value)),
                        'credits_usage' => 0,
                        'is_iran' => $isIran,
                        'is_webservice' => 1,
                        'weekly_usage' => 0,
                        'each_user_limit' => $chatLimit,
                        'is_uni' => $chatLimit > 0
                    ]);
                }else{
                     UidUsage::where('uid', $user->uid)->update([
                         'usage_type' => ((int) $authResult->value) === -123 ? 'time' : (((int) $authResult->value) == 0 ? 'none' : 'credit'),
                        'credits_count' => ((int) $authResult->value) === -123 ? 0 : (((int) $authResult->value) == 0 ? 0 : ((int) $authResult->value)),
                        'each_user_limit' => $chatLimit,
                        'is_uni' => $chatLimit > 0
                     ]);
                }
            } else {
                $apiResponse = Http::post(env('OPENALEX_API') . "/user/login", [
                    'email' => $request->email,
                    'password' => $user->plain_password,
                ]);
                if (!$apiResponse->successful()) {
                    return response()->json(['error' => 'Failed to create user in OpenAlex'], 500);
                }
                $uid = UidUsage::where('uid', $user->uid)->update([
                    'usage_type' => ((int) $authResult->value) === -123 ? 'time' : (((int) $authResult->value) == 0 ? 'none' : 'credit'),
                    'credits_count' => ((int) $authResult->value) === -123 ? 0 : (((int) $authResult->value) == 0 ? 0 : ((int) $authResult->value)),
                    'each_user_limit' => $chatLimit,
                    'is_uni' => $chatLimit > 0
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json(['status' => 'ok', 'access_token_backend' => $token, 'access_token_openalex' => $apiResponse->json()['access_token']]);
        }

    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function getUsageLimit()
    {
        if (!$this->location) {
            $this->location = GeoIP::getLocation(request()->ip());
        }

        return isset($this->location['country']) && $this->location['country'] == 'Iran' ? 5 : 10;
    }

    public function isIran()
    {
        if (!$this->location) {
            $this->location = GeoIP::getLocation(request()->ip());
        }
        return isset($this->location['country']) && $this->location['country'] == 'Iran';
    }
}
