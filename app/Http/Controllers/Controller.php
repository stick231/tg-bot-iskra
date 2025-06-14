<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function PHPUnit\Framework\isArray;

abstract class Controller
{
    protected array $stateFields = [];

    abstract protected function promptForField(string $field): string;

    protected function handleRequest(array $data, string $message)
    {
        $response = Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", [
            'chat_id' => $data['chat']['id'],
            'text' => $message,
            'parse_mode' => 'Markdown',
        ])->json();

        if (isset($response['ok']) && $response['ok'] === true) {
            $response['status'] = 200;
        } else {
            $response['status'] = 500;
        }

        return $response;
    }

    protected function onStateComplete(UserState $state): void
    {
    }

    protected function handleState(UserState $state, $dataRequest): string
    {
        $field = $state->waiting_for;
        $data  = $state->data ?? [];
        $data[$field] = $dataRequest['text'];
        $state->data = $data;

        $idx = array_search($field, $this->stateFields, true);

        if ($idx === count($this->stateFields) - 1) {
            $data['owner_id'] = $dataRequest['from']['id'];
            $data['status'] = 'in_progress';
            $state->data = $data;
            $this->onStateComplete($state);
            
            return "✅ Successfully completed!";
        }

        $next = $this->stateFields[$idx + 1];
        $state->waiting_for = $next;
        $state->save();

        return $this->promptForField($next);
    }


    protected function process($data, string $triggerCommand)
    {
        $state = UserState::firstOrCreate(
            ['telegram_id' => $data['from']['id']],
            ['data' => [], 'waiting_for' => null, 'trigger_command' => null]
        );

        
        if (is_null($state->waiting_for)) {
            $state->state = 'wait';
            $state->trigger_command = $triggerCommand;
            $state->waiting_for   = $this->stateFields[0];
            $state->save();

            $message = $this->promptForField($state->waiting_for);
        } else {
            $message = $this->handleState($state, $data);
        }

        $response = $this->handleRequest($data, $message);
        return response()->json(['status' => $response['status']]);
    }

    public function handleTasksPagination($data)
    {

    }
}
