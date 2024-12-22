<?php

namespace App\Http\Controllers;

use App\Models\Chats;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatsController extends Controller
{
    //
    public function index()
    {
        $users = User::select('id', 'name')->get();

        return view('Chat.Index', compact('users'));
    }
    public function sendMessage(Request $request)
    {
        $item = new Chats();
        $item->date_time = now();
        $item->send_by = Auth::user()->id;
        $item->send_to = $request->user;
        $item->message_type = 'text';
        $item->message = e($request->input('message')); // Use the e helper
        $item->save();

        return $item;
    }

    public function getNewMessages($user_id)
    {
        $message = Chats::where('send_to', Auth::user()->id)
            ->where('send_by', $user_id)
            ->where('is_received', 0)
            ->with('sender')
            ->first();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        if ($message) {
            $eventData = [
                'item' => $message,
            ];

            echo "data:" . json_encode($eventData) . "\n\n";
            $message->is_received = 1;
            $message->update();
        } else {
            echo "\n\n";
        }

        ob_flush();
        flush();
    }

    public function getChatHistory(Request $request)
    {
        $messages = Chats::with('sender')
            ->where(function ($query) use ($request) {
                $query->where('send_by', Auth::user()->id)
                    ->where('send_to', $request->userID);
            })
            ->orWhere(function ($query) use ($request) {
                $query->where('send_by', $request->userID)
                    ->where('send_to', Auth::user()->id);
            })
            ->orderBy('date_time', 'asc')
            ->get();

        foreach ($messages as $message) {
            $message->is_received = 1;
            $message->update();
        }
        return $messages;
    }

    public function uploadImage(Request $request)
    {
        // Validate the incoming request with necessary rules
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:1024',
        ]);

        // Check if the request contains a file
        if ($request->hasFile('image')) {
            // Get the file from the request
            $image = $request->file('image');

            // Generate a unique name for the file
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            // Move the file to the storage path (public/images in this example)
            $image->move(public_path('communication/images'), $imageName);

            // You can save the file path in the database if needed
            $filePath = 'communication/images/' . $imageName;


            $chat = new Chats();
            $chat->message = $filePath;
            $chat->date_time = now();
            $chat->send_by = Auth::user()->id;
            $chat->send_to = $request->userID;
            $chat->message_type = 'attachment';
            $chat->save();
            // Return a response, e.g., the file path or a success message
            return $chat;
        }

        // Return an error response if no file is found
        return response()->json(['status' => 'error', 'message' => 'No image file found'], 400);
    }
}
