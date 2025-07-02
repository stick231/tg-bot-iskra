<?php

namespace App\Http\Controllers;

use App\Models\UserState;
use Illuminate\Support\Facades\Http;

abstract class Controller
{
    protected array $stateFields = [];

    abstract protected function promptForField(string $field): string;

    protected function handleRequest(array $data, string $message, $param = [])
    {
        $param = $param === [] ? ['reply_markup' => json_encode(['remove_keyboard' => true ])] : $param;
        $response = Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", array_merge([
            'chat_id' => $data['chat']['id'] ?? $data['message']['chat']['id'],
            'text' => $message,
            'parse_mode' => 'Markdown',
        ], $param))->json();

        if (isset($response['ok']) && $response['ok'] === true) {
            $response['status'] = 200;
        } else {
            $response['status'] = 500;
        }

        return $response;
    }

    protected function onStateComplete(UserState $state)
    {
    }

    protected function handleState(UserState $state, $dataRequest, $custom_field)
    {
        $field = $state->waiting_for;
        $data  = $state->data ?? [];
        $data[$field] = $dataRequest['text'];
        $state->data = $data;
        
        $field_array = $custom_field === [] ? $this->stateFields : $custom_field;
        
        $idx = array_search($field, $field_array, true);

        if ($idx === count($field_array) - 1) {
            $data['owner_id'] = $dataRequest['from']['id']; //rewrite
            $data['status'] = 'in_progress'; //rewrite
            $state->data = $data;
            $response = $this->onStateComplete($state);
            if(isset($response) || $response === true){
                return '✅ Successfully completed!'; // change to adaptive response
            }
        }
        $next = $field_array[$idx + 1];
        $state->waiting_for = $next;
        $state->save();

        return $this->promptForField($next);//fix error callback_data
    }


    protected function process($data, string $triggerCommand, $custom_field = [])
    {
        $state = UserState::firstOrCreate(
            ['telegram_id' => $data['from']['id']],
            ['data' => [], 'waiting_for' => null, 'trigger_command' => null]
        );

        
        if (is_null($state->waiting_for)) {
            $state->state = 'wait';
            $state->trigger_command = $triggerCommand;
            $state->waiting_for   = empty($custom_field) ? $this->stateFields[0] : $custom_field[0];
            $state->save();

            $message = $this->promptForField($state->waiting_for);
        } elseif(isset($data['message_response'])){
            $message = $data['message_response'];
        } else {
            $message = $this->handleState($state, $data, $custom_field);
        }
        $param = isset($data['param']) ? $data['param'] : [];

        $response = $this->handleRequest($data, $message, $param);
        return response()->json(['status' => $response['status'] ?? null]);
    }
}
