<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function sendPromo(Request $request)
    {
        $this->validate($request, [
            'customer_id' => 'required|exists:users,id',
            'service_name' => 'required|string',
            'discount_rate' => 'nullable|string'
        ]);

        $user = User::findOrFail($request->customer_id);

        $success = $this->whatsappService->sendPromo($user->phone, [
            'customer_name' => $user->name,
            'service_name' => $request->service_name,
            'discount_rate' => $request->discount_rate ?? '50'
        ]);

        return response()->json(['success' => $success]);
    }

    public function sendBirthday(Request $request)
    {
        $this->validate($request, [
            'customer_id' => 'required|exists:users,id',
            'discount' => 'nullable|string',
            'expiry_date' => 'nullable|string'
        ]);

        $user = User::findOrFail($request->customer_id);

        $success = $this->whatsappService->sendBirthdayGreeting(
            $user->phone,
            $user->name,
            $request->discount ?? '30%',
            $request->expiry_date
        );

        return response()->json(['success' => $success]);
    }

    public function sendText(Request $request)
    {
        $this->validate($request, [
            'phone' => 'required|string',
            'message' => 'required|string'
        ]);

        $success = $this->whatsappService->sendMessage($request->phone, $request->message);

        return response()->json(['success' => $success]);
    }
}
