<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{
    public function createAccount(Request $request) {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                "message" => "No hay token"
            ], 404);
        }

        $user = PersonalAccessToken::findToken($token)?->tokenable;

        if (!$user) {
            return response()->json([
                'msg' => 'Usuario no encontrado.'
            ], 404);
        }

        $validate = Validator::make($request->all(), [ 
            "type" => "required|string",
            "nickname" => "required|string"
        ]);

        if ($validate->fails()) {
            return response()->json([
                'errors' => $validate->errors()
            ], 422);
        }

        do {
            $numeroTarjeta = $this->generarNumeroTarjeta();
        } while (Account::where('card_number', $numeroTarjeta)->exists());

        try {
            $response = Http::post('http://api.nessieisreal.com/customers/' . $user->id_nessie . '/accounts?key=e74c2feafa6f8b24c71ded25e2baeb2e', [
                'type' => $request->type,
                'nickname' => $request->nickname,
                'rewards' => 0,
                'balance' => 0,
                'account_number' => $numeroTarjeta
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Error al crear cuenta en Nessie',
                    'message' => $response->json()
                ], $response->status());
            }

            $responseData = $response->json();
            $accountId = $responseData['objectCreated']['_id'];

            $account = Account::create([
                'card_number' => $numeroTarjeta
            ]);

            return response()->json([
                'msg' => 'Cuenta creada exitosamente',
                'account' => $account
            ], 201);

        } catch (\Exception $e) {
            // Si falla la API, eliminar la cuenta local si se creó
            if (isset($account)) {
                $account->delete();
            }
            
            return response()->json([
                'error' => 'Error al crear cuenta',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function generarNumeroTarjeta() {
        $numero = '';
        for ($i = 0; $i < 16; $i++) {
            $numero .= random_int(0, 9);
        }
        return $numero;
    }
}
