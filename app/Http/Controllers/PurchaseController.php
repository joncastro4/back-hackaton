<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;

class PurchaseController extends Controller
{
    public function getPurchasesLocations(Request $request) {
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

        $nessieId = $user->id_nessie;

        $requestAccountsCustomer = Http::get("http://api.nessieisreal.com/customers/" . $nessieId . "/accounts?key=e74c2feafa6f8b24c71ded25e2baeb2e");

        if (!$requestAccountsCustomer->successful()) {
            return response()->json([
                'error' => 'Error al obtener cuentas'
            ], $requestAccountsCustomer->status());
        }

        $accounts = $requestAccountsCustomer->json();
        $accountIds = collect($accounts)->pluck('_id')->toArray();

        // Paso 1: Obtener todas las compras de todas las cuentas (concurrente)
        $purchaseRequests = [];
        foreach ($accountIds as $accountId) {
            $purchaseRequests[] = Http::async()->get("http://api.nessieisreal.com/accounts/{$accountId}/purchases?key=e74c2feafa6f8b24c71ded25e2baeb2e");
        }

        $purchaseResponses = Http::pool(fn ($pool) => array_map(
            fn ($accountId) => $pool->get("http://api.nessieisreal.com/accounts/{$accountId}/purchases?key=e74c2feafa6f8b24c71ded25e2baeb2e"),
            $accountIds
        ));

        // Recopilar todas las compras
        $allPurchases = [];
        foreach ($purchaseResponses as $response) {
            if ($response->successful()) {
                $purchases = $response->json();
                foreach ($purchases as $purchase) {
                    $allPurchases[] = $purchase;
                }
            }
        }

        // Paso 2: Obtener información de merchants (concurrente)
        $merchantIds = array_unique(array_column($allPurchases, 'merchant_id'));
        
        $merchantResponses = Http::pool(fn ($pool) => array_map(
            fn ($merchantId) => $pool->get("http://api.nessieisreal.com/merchants/{$merchantId}?key=e74c2feafa6f8b24c71ded25e2baeb2e"),
            $merchantIds
        ));

        // Crear un mapa de merchants por ID
        $merchantsMap = [];
        foreach (array_combine($merchantIds, $merchantResponses) as $merchantId => $response) {
            if ($response->successful()) {
                $merchantsMap[$merchantId] = $response->json();
            }
        }

        // Paso 3: Combinar datos
        $purchasesData = [];
        foreach ($allPurchases as $purchase) {
            $merchantId = $purchase['merchant_id'];
            
            if (isset($merchantsMap[$merchantId])) {
                $merchant = $merchantsMap[$merchantId];
                
                $purchasesData[] = [
                    'name' => $merchant['name'],
                    'lat' => $merchant['geocode']['lat'],
                    'lng' => $merchant['geocode']['lng'],
                    'amount' => $purchase['amount']
                ];
            }
        }

        return response()->json($purchasesData);
    }
}