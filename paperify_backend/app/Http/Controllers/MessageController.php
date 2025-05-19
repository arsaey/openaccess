<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Token;
use App\Models\UidUsage;
use Illuminate\Http\Request;

class MessageController extends Controller
{


    public function messagesList(Request $request)
    {
        $messages = Message::where('chat_id', auth()->user()->id . '_' . $request->get('id'))->where('show', 1)->orderBy('id', 'asc')->get();
        $final = [];
        foreach ($messages as $message) {
            $text = ($message->text);
            list($text, $questions) = $this->extractAndModifyText($text); // Extract and modify text
            $final[] = ['author' => $message->role == 'user' ? 'me' : $message->role, 'type' => $message->role == 'assistant' ? 'html' : ($message->role == 'system' ? 'system' : 'text'), 'data' => ['text' => $text], 'suggestions' => $questions];
        }
        $uidUsage = UidUsage::where('uid', auth()->user()->uid)->first();
        if ($uidUsage->type == 'none') {
            if ($uidUsage->remainFreeCredit() > 0) {
                $final[] = ['author' => 'system', 'type' => 'system', 'data' => ['text' => 'you have ' . $uidUsage->remainFreeCredit() . ' credit(s) left', 'meta' => date('Y-m-d H:i:s')]];
            } else {
                $final[] = ['author' => 'system', 'type' => 'system', 'data' => ['text' => 'you already used all your credits', 'meta' => date('Y-m-d H:i:s')]];
            }
        } else {
            if ($uidUsage->type == 'credit') {
                $final[] = ['author' => 'system', 'type' => 'system', 'data' => ['text' => 'you have ' . $uidUsage->remainPurchedCredit() . ' credit(s) left', 'meta' => date('Y-m-d H:i:s')]];
            } else {
                $final[] = ['author' => 'system', 'type' => 'system', 'data' => ['text' => 'you have active subscription', 'meta' => date('Y-m-d H:i:s')]];
            }
        }


        return response()->json(['status' => 'ok', 'messages' => $final]);
    }

    public function sendMessage(Request $request)
    {

        $uidUsage = UidUsage::where('uid', auth()->user()->uid)->first();
        $weekly_free_usage = 0;
        if (($uidUsage->type == 'credit' && !$uidUsage->remainPurchedCredit())) {
            $uidUsage->type == 'none';
        }
        if (($uidUsage->type == 'none' && $uidUsage->remainFreeCredit()) || ($uidUsage->type == 'credit' && $uidUsage->remainPurchedCredit()) || $uidUsage->type == 'time') {

            if ($uidUsage->type == 'time' && auth()->user()->weekly_usage > 300) {
                return response()->json(['type' => 'assistant', 'text' => "Please try again in a few hours; we're experiencing a high volume of requests", 'suggestions' => []]);
            }

            $messages = Message::where('chat_id', auth()->id() . '_' . $request->id)->orderBy('id', 'asc')->get();
            $final = [];
            foreach ($messages as $i => $message) {
                if ($i > 0)
                    break;
                $text = ($message->text);
                list($text, $questions) = $this->extractAndModifyText($text); // Extract and modify text
                $final[] = ['role' => $message->role, 'content' => $text];
            }

            if (count($final) == 0) {
                $final = [
                    [
                        'role' => 'system',
                        'content' => 'You are "Paperify," an expert in analyzing and summarizing scientific articles. Your role is to provide concise (under 300 words), realistic, scientific, and actionable responses based solely on the provided data. Avoid mentioning your identity as an AI or ChatGPT. Always conclude your responses with two  suggestions for further discussion, exactly formatted as  (suggestionsList: suggestion_text; suggestion_text; suggestionsList) to facilitate extraction- never change this format its very important to keep it that way never change the suggestionsList part because i use that for extraction. If an article’s URL or content (e.g., PDF, HTML) is available, analyze it directly for accuracy. If access is unavailable, assume the provided data is accurate and base your interpretation on that. Data: ' . json_encode($request->get('data'))
                    ],
                ];
                Message::create([
                    'user_id' => auth()->user()->id,
                    'role' => 'system',
                    'chat_id' => auth()->id() . '_' . $request->id,
                    'text' => $final[0]['content'],
                    'show' => 0
                ]);
            }

            $newMessage = ['role' => 'user', 'content' => $request->get('text')];
            Message::create([
                'user_id' => auth()->user()->id,
                'role' => 'user',
                'chat_id' => auth()->id() . '_' . $request->id,
                'text' => $newMessage['content'],
                'show' => 1,
                'is_weekly_free_usage' => $uidUsage->type == 'none'
            ]);

            $final[] = $newMessage;
            $result = \App\Services\Chatgpt::client()->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $final
            ]);


            $text = ($result->choices[0]->message->content); // Get the message content
            Message::create([
                'user_id' => auth()->user()->id,
                'role' => 'assistant',
                'chat_id' => auth()->id() . '_' . $request->id,
                'text' => $text,
                'show' => 1
            ]);
            list($text, $questions) = $this->extractAndModifyText($text); // Extract and modify text
            $uidUsagetype = $uidUsage->type;

            $uidUsage = UidUsage::where('uid', auth()->user()->uid)->first();
            $uidUsage->weekly_usage = $uidUsage->weekly_usage + 1;
            if ($uidUsagetype == 'credit') {
                $uidUsage->credits_usage = $uidUsage->credits_usage + 1;
            }
            $uidUsage->update();
            $user = auth()->user();
            $user->weekly_usage = $user->weekly_usage + 1;

            if ($uidUsagetype == 'credit') {
                $user->credits_usage = $user->credits_usage + 1;
            }

            $user->update();

            $remainCredit = $uidUsagetype == 'time' ? 'time' : ($uidUsagetype == 'none' ? $uidUsage->remainFreeCredit() : $uidUsage->remainPurchedCredit());
            return response()->json(['type' => 'assistant', 'text' => $text, 'suggestions' => $questions, 'remain_credit' => $remainCredit]);
        } else {
            return response()->json(data: ['type' => 'assistant', 'text' => 'you have no credit left', 'suggestions' => []]);
        }
    }

    private function extractAndModifyText($text)
    {
        preg_match('/suggestionsList:\s*(.*?)\s*suggestionsList/', $text, $matches);

        $questions = [];
        if (isset($matches[1])) {
            // Split the extracted string into individual questions
            $questions = array_map('trim', explode(';', $matches[1]));

            if (count($questions) < 2) {
                $questions = array_map('trim', explode('؛', $matches[1]));
            }
            if (count($questions) < 2) {
                $questions = array_map('trim', explode(';', $matches[1]));
            }

            // Remove any empty values
            $questions = array_filter($questions);

            // Replace the suggestions section from the text
            $text = preg_replace('/suggestionsList:\s*.*?\s*suggestionsList/', '', $text);
            $text = str_replace('()', '', $text);
        }

        return [$text, $questions];
    }

}
