<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

use function Laravel\Prompts\error;

class AuthController extends Controller {
  public function register(Request $request) {
    $validate = Validator::make($request->all(), [
        'firstName' => 'required|max:255',
        'lastName' => 'required|max:255',
        'username' => 'required|max:255',
        'email' => 'required|email|unique:users|max:255',
        'password' => 'required|string|min:8|max:255',
        'address.street_number' => 'required|string',
        'address.street_name' => 'required|string|max:255',
        'address.city' => 'required|string|max:255',
        'address.state' => 'required|string|max:2',
        'address.zip' => 'required|string',
    ]);

    if ($validate->fails()) {
      return response()->json([
        $validate->errors()
      ], 422);
    }

    try {
      $response = Http::post('http://api.nessieisreal.com/customers?key=e74c2feafa6f8b24c71ded25e2baeb2e', [
          'first_name' => $request->firstName,
          'last_name' => $request->lastName,
          'address' => [
              'street_number' => $request->input('address.street_number'),
              'street_name' => $request->input('address.street_name'),
              'city' => $request->input('address.city'),
              'state' => $request->input('address.state'),
              'zip' => $request->input('address.zip')
          ]
      ]);

      $responseData = $response->json();
      $customerId = $responseData['objectCreated']['_id'];

      $user = User::create([
        'name' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'id_nessie' => $customerId
      ]);

      $token = $user->createToken('auth_token')->plainTextToken;

      return response()->json([
        "message" => "Usuario creado",
        "user" => $user,
        "token" => $token
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'error' => 'Failed to create remote customer',
        'message' => $e->getMessage()
      ], 500);
    }
  }

  public function login(Request $request) {
    $validate = Validator::make($request->all(), [
      'email' => "required|email",
      'password' => "required"
    ]);

    if ($validate->fails()) {
      return response()->json([
        $validate->errors()
      ], 422);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'msg' => 'Credenciales invalidas'
        ], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'msg' => 'Sesión iniciada.',
        'token' => $token
    ], 200);
  }

  public function nessieId(Request $request) {
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

    return response()->json([
      "id_nessie" => $user->id_nessie
    ]);
  }
}
